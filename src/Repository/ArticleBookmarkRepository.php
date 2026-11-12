<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ArticleBookmark;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ArticleBookmark> */
class ArticleBookmarkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ArticleBookmark::class);
    }

    public function findOneFor(int $articleId, int $userId): ?ArticleBookmark
    {
        return $this->findOneBy(['article' => $articleId, 'user' => $userId]);
    }

    /** @return list<ArticleBookmark> */
    public function findForUser(int $userId): array
    {
        return $this->findBy(['user' => $userId], ['createdAt' => 'DESC']);
    }
}
