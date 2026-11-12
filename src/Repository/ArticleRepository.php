<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Article;
use App\Enum\ArticleStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
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

    public function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        $builder = $this->createQueryBuilder('article')
            ->select('COUNT(article.id)')
            ->andWhere('article.slug = :slug')
            ->setParameter('slug', $slug);
        if (null !== $ignoreId) {
            $builder->andWhere('article.id != :id')->setParameter('id', $ignoreId);
        }

        return (int) $builder->getQuery()->getSingleScalarResult() > 0;
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

    /**
     * @param array{q?: string, status?: string, author?: int, category?: int} $filters
     *
     * @return array{items: list<Article>, total: int}
     */
    public function findForAdmin(array $filters, int $page, int $perPage, bool $includeTrashed = false): array
    {
        $filtersBag = $this->getEntityManager()->getFilters();
        $enabled = $filtersBag->isEnabled('softdeleteable');
        if ($enabled) {
            $filtersBag->disable('softdeleteable');
        }

        try {
            $builder = $this->createQueryBuilder('article')
                ->leftJoin('article.author', 'author')->addSelect('author')
                ->leftJoin('article.categories', 'category')->addSelect('category')
                ->orderBy('article.updatedAt', 'DESC');

            $builder->andWhere($includeTrashed ? 'article.deletedAt IS NOT NULL' : 'article.deletedAt IS NULL');

            if ('' !== ($filters['q'] ?? '')) {
                $builder->andWhere('article.title LIKE :search OR article.excerpt LIKE :search')
                    ->setParameter('search', '%'.$filters['q'].'%');
            }
            if ('' !== ($filters['status'] ?? '')) {
                $builder->andWhere('article.status = :status')
                    ->setParameter('status', ArticleStatus::from($filters['status']));
            }
            if (isset($filters['author'])) {
                $builder->andWhere('author.id = :author')->setParameter('author', $filters['author']);
            }
            if (isset($filters['category'])) {
                $builder->andWhere('category.id = :category')->setParameter('category', $filters['category']);
            }

            $query = $builder->getQuery()
                ->setFirstResult(max(0, ($page - 1) * $perPage))
                ->setMaxResults($perPage);

            $paginator = new Paginator($query, fetchJoinCollection: true);
            /** @var list<Article> $items */
            $items = iterator_to_array($paginator->getIterator());

            return ['items' => $items, 'total' => count($paginator)];
        } finally {
            if ($enabled) {
                $filtersBag->enable('softdeleteable');
            }
        }
    }

    public function findIncludingDeleted(int $id): ?Article
    {
        $filters = $this->getEntityManager()->getFilters();
        $enabled = $filters->isEnabled('softdeleteable');
        if ($enabled) {
            $filters->disable('softdeleteable');
        }

        try {
            return $this->find($id);
        } finally {
            if ($enabled) {
                $filters->enable('softdeleteable');
            }
        }
    }

    /** @return list<Article> */
    public function findScheduledDue(\DateTimeImmutable $now): array
    {
        return $this->createQueryBuilder('article')
            ->andWhere('article.status = :status')
            ->andWhere('article.scheduledAt IS NOT NULL')
            ->andWhere('article.scheduledAt <= :now')
            ->setParameter('status', ArticleStatus::Scheduled)
            ->setParameter('now', $now)
            ->orderBy('article.scheduledAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countByStatus(ArticleStatus $status): int
    {
        return (int) $this->createQueryBuilder('article')
            ->select('COUNT(article.id)')
            ->andWhere('article.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
