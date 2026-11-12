<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Article;
use App\Entity\Comment;
use App\Enum\CommentStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
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
            ->setParameter('article', $articleId)
            ->setParameter('status', CommentStatus::Approved)
            ->orderBy('comment.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<Comment> */
    public function findApprovedFlat(Article $article): array
    {
        return $this->createQueryBuilder('comment')
            ->leftJoin('comment.user', 'user')->addSelect('user')
            ->leftJoin('comment.parent', 'parent')->addSelect('parent')
            ->andWhere('comment.article = :article')
            ->andWhere('comment.status = :status')
            ->setParameter('article', $article)
            ->setParameter('status', CommentStatus::Approved)
            ->orderBy('comment.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countApprovedForArticle(Article $article): int
    {
        return (int) $this->createQueryBuilder('comment')
            ->select('COUNT(comment.id)')
            ->andWhere('comment.article = :article')
            ->andWhere('comment.status = :status')
            ->setParameter('article', $article)
            ->setParameter('status', CommentStatus::Approved)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param array{status?: string, q?: string} $filters
     *
     * @return array{items: list<Comment>, total: int, pages: int}
     */
    public function findForAdmin(array $filters, int $page, int $perPage = 20, ?int $restrictToAuthorId = null): array
    {
        $builder = $this->createQueryBuilder('comment')
            ->leftJoin('comment.article', 'article')->addSelect('article')
            ->leftJoin('comment.user', 'user')->addSelect('user')
            ->leftJoin('article.author', 'articleAuthor')->addSelect('articleAuthor')
            ->orderBy('comment.createdAt', 'DESC');

        if (isset($filters['status']) && '' !== $filters['status']) {
            $builder->andWhere('comment.status = :status')->setParameter('status', CommentStatus::from($filters['status']));
        }
        if (isset($filters['q']) && '' !== trim($filters['q'])) {
            $builder->andWhere('comment.content LIKE :query OR comment.guestName LIKE :query')
                ->setParameter('query', '%'.trim($filters['q']).'%');
        }
        if (null !== $restrictToAuthorId) {
            $builder->andWhere('articleAuthor.id = :author')->setParameter('author', $restrictToAuthorId);
        }

        $query = $builder->getQuery()
            ->setFirstResult(max(0, ($page - 1) * $perPage))
            ->setMaxResults($perPage);
        $paginator = new Paginator($query);

        return ['items' => iterator_to_array($paginator->getIterator()), 'total' => count($paginator), 'pages' => max(1, (int) ceil(count($paginator) / $perPage))];
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
