<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Article;
use App\Enum\ArticleStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Article> */
class ArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Article::class);
    }

    public function findOneBySlug(string $slug): ?Article
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    /** @return list<Article> */
    public function findPublished(int $limit = 10): array
    {
        return $this->createQueryBuilder('article')
            ->andWhere('article.status = :status')
            ->andWhere('article.publishedAt <= :now')
            ->setParameter('status', ArticleStatus::Published)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('article.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
