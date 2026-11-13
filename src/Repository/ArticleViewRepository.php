<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ArticleView;
use App\Enum\ArticleStatus;
use App\Enum\ArticleVisibility;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ArticleView> */
class ArticleViewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ArticleView::class);
    }

    public function countSince(\DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('view')
            ->select('COUNT(view.id)')
            ->andWhere('view.viewedAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function hasRecentView(int $articleId, string $visitorHash, \DateTimeImmutable $since): bool
    {
        return null !== $this->createQueryBuilder('view')
            ->andWhere('view.article = :article')
            ->andWhere('view.visitorHash = :hash')
            ->andWhere('view.viewedAt >= :since')
            ->setParameter('article', $articleId)
            ->setParameter('hash', $visitorHash)
            ->setParameter('since', $since)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<int> Article ids ordered by view count since the given time. */
    public function topArticleIdsSince(\DateTimeImmutable $since, int $limit = 5): array
    {
        $rows = $this->createQueryBuilder('view')
            ->select('IDENTITY(view.article) AS articleId, COUNT(view.id) AS total')
            ->join('view.article', 'article')
            ->andWhere('article.deletedAt IS NULL AND article.status = :published AND article.visibility = :public AND article.isPremium = false AND article.publishedAt <= :now')
            ->setParameter('published', ArticleStatus::Published)->setParameter('public', ArticleVisibility::Public)->setParameter('now', new \DateTimeImmutable())
            ->andWhere('view.viewedAt >= :since')
            ->andWhere('view.viewedAt <= :now')
            ->setParameter('since', $since)
            ->groupBy('view.article')
            ->orderBy('total', 'DESC')
            ->addOrderBy('articleId', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_map(intval(...), array_column($rows, 'articleId'));
    }

    /** @return list<int> Recent 24h rate relative to the preceding six-day baseline. */
    public function trendingArticleIds(\DateTimeImmutable $now, int $limit = 3): array
    {
        if ($limit < 1) {
            return [];
        }
        $sql = "SELECT view.article_id,
            SUM(CASE WHEN view.viewed_at >= :recent THEN 1 ELSE 0 END) AS recent_views,
            SUM(CASE WHEN view.viewed_at >= :recent THEN 1 ELSE 0 END) * 6.0 /
                (SUM(CASE WHEN view.viewed_at < :recent THEN 1 ELSE 0 END) + 6) AS velocity
            FROM article_view view JOIN article article ON article.id = view.article_id
            WHERE article.deleted_at IS NULL AND article.status = 'published' AND article.visibility = 'public'
                AND article.is_premium = 0 AND article.published_at <= :now
                AND view.viewed_at >= :since AND view.viewed_at <= :now
            GROUP BY view.article_id HAVING recent_views > 0
            ORDER BY velocity DESC, recent_views DESC, view.article_id DESC";
        $sql = $this->getEntityManager()->getConnection()->getDatabasePlatform()->modifyLimitQuery($sql, max(0, $limit));
        $ids = $this->getEntityManager()->getConnection()->fetchFirstColumn($sql,
            ['recent' => $now->modify('-24 hours'), 'since' => $now->modify('-7 days'), 'now' => $now],
            ['recent' => Types::DATETIME_IMMUTABLE, 'since' => Types::DATETIME_IMMUTABLE, 'now' => Types::DATETIME_IMMUTABLE],
        );

        return array_map(intval(...), $ids);
    }
}
