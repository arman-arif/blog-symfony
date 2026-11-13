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

    public function countForAuthor(?int $authorId = null): int
    {
        $builder = $this->createQueryBuilder('bookmark')->select('COUNT(bookmark.id)');
        if (null !== $authorId) {
            $builder->join('bookmark.article', 'article')->andWhere('article.author = :scopeAuthor')->setParameter('scopeAuthor', $authorId);
        }

        return (int) $builder->getQuery()->getSingleScalarResult();
    }

    /** @param list<int> $articleIds
     * @return array<int, int>
     */
    public function countsByArticleIds(array $articleIds): array
    {
        if ([] === $articleIds) {
            return [];
        }

        $rows = $this->createQueryBuilder('bookmark')
            ->select('IDENTITY(bookmark.article) AS articleId, COUNT(bookmark.id) AS total')
            ->andWhere('bookmark.article IN (:ids)')
            ->setParameter('ids', $articleIds)
            ->groupBy('bookmark.article')
            ->getQuery()
            ->getArrayResult();

        return array_column($rows, 'total', 'articleId');
    }
}
