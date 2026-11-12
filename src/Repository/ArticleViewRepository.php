<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ArticleView;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
            ->andWhere('view.viewedAt >= :since')
            ->setParameter('since', $since)
            ->groupBy('view.article')
            ->orderBy('total', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_map(intval(...), array_column($rows, 'articleId'));
    }
}
