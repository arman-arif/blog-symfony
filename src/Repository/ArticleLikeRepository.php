<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ArticleLike;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ArticleLike> */
class ArticleLikeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ArticleLike::class);
    }

    public function findOneFor(int $articleId, int $userId): ?ArticleLike
    {
        return $this->findOneBy(['article' => $articleId, 'user' => $userId]);
    }
}
