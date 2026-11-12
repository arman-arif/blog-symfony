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
}
