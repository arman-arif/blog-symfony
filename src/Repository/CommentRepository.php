<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Comment;
use App\Enum\CommentStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Comment> */
class CommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Comment::class);
    }

    /** @return list<Comment> */
    public function findApprovedForArticle(int $articleId): array
    {
        return $this->createQueryBuilder('comment')
            ->andWhere('comment.article = :article')
            ->andWhere('comment.status = :status')
            ->andWhere('comment.parent IS NULL')
            ->setParameter('article', $articleId)
            ->setParameter('status', CommentStatus::Approved)
            ->orderBy('comment.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countByStatus(CommentStatus $status): int
    {
        return (int) $this->createQueryBuilder('comment')
            ->select('COUNT(comment.id)')
            ->andWhere('comment.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
