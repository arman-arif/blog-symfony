<?php

declare(strict_types=1);

namespace App\Service\Analytics;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final readonly class AnalyticsRollupService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        #[Autowire(service: 'cache.content')]
        private TagAwareCacheInterface $cache,
    ) {
    }

    /** Aggregate raw views into daily stats. Safe to run repeatedly: daily values are set, not incremented. */
    public function aggregateRecent(int $days = 2): int
    {
        $since = (new \DateTimeImmutable('today'))->modify('-'.$days.' days');
        $connection = $this->entityManager->getConnection();
        /** @var list<array{article_id: int|string, day: string, total: int|string}> $rows */
        $rows = $connection->fetchAllAssociative(
            'SELECT article_id, DATE(viewed_at) AS day, COUNT(*) AS total FROM article_view WHERE viewed_at >= :since GROUP BY article_id, DATE(viewed_at)',
            ['since' => $since->format('Y-m-d H:i:s')],
        );

        $now = new \DateTimeImmutable();
        foreach ($rows as $row) {
            $connection->executeStatement(
                'INSERT INTO article_daily_stat (article_id, stat_day, views, created_at, updated_at) VALUES (:article, :day, :views, :created, :updated) ON DUPLICATE KEY UPDATE views = VALUES(views), updated_at = VALUES(updated_at)',
                [
                    'article' => (int) $row['article_id'],
                    'day' => (string) $row['day'],
                    'views' => (int) $row['total'],
                    'created' => $now->format('Y-m-d H:i:s'),
                    'updated' => $now->format('Y-m-d H:i:s'),
                ],
            );
        }

        $this->cache->invalidateTags(['analytics', 'homepage']);

        return count($rows);
    }

    public function pruneRawViews(int $retentionDays = 30): int
    {
        $cutoff = (new \DateTimeImmutable('today'))->modify('-'.$retentionDays.' days');

        /** @var int|string $deleted */
        $deleted = $this->entityManager->createQueryBuilder()
            ->delete(\App\Entity\ArticleView::class, 'view')
            ->andWhere('view.viewedAt < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->execute();
        $this->cache->invalidateTags(['analytics']);

        return (int) $deleted;
    }
}
