<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Article;
use App\Enum\ArticleStatus;
use App\Enum\ArticleVisibility;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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

    private function publishedBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('article')
            ->leftJoin('article.author', 'author')->addSelect('author')
            ->leftJoin('article.categories', 'category')->addSelect('category')
            ->leftJoin('article.tags', 'tag')->addSelect('tag')
            ->leftJoin('article.featuredImage', 'featuredImage')->addSelect('featuredImage')
            ->andWhere('article.status = :published')
            ->andWhere('article.publishedAt IS NOT NULL')
            ->andWhere('article.publishedAt <= :now')
            ->andWhere('article.isPremium = false')
            ->setParameter('published', ArticleStatus::Published)
            ->setParameter('now', new \DateTimeImmutable());
    }

    /**
     * @param array{q?: string, category?: string, tag?: string, author?: string, sort?: string} $filters
     *
     * @return array{items: list<Article>, total: int, pages: int}
     */
    public function paginatePublished(array $filters, int $page, int $perPage = 9): array
    {
        $builder = $this->publishedBuilder();

        if ('' !== ($filters['q'] ?? '')) {
            $ids = $this->searchPublishedIds($filters['q']);
            $terms = array_slice(array_filter(preg_split('/\s+/', trim($filters['q'])) ?: [], static fn (string $term): bool => mb_strlen($term) >= 2), 0, 5);
            $conditions = [[] !== $ids ? 'article.id IN (:ids)' : '1 = 0'];
            if ([] !== $ids) {
                $builder->setParameter('ids', $ids);
            }
            foreach ($terms as $index => $term) {
                $conditions[] = 'article.title LIKE :term'.$index;
                $conditions[] = 'article.excerpt LIKE :term'.$index;
                $conditions[] = 'article.content LIKE :term'.$index;
                $builder->setParameter('term'.$index, '%'.$term.'%');
            }
            $builder->andWhere($builder->expr()->orX(...$conditions));
        }
        if ('' !== ($filters['category'] ?? '')) {
            $builder->andWhere('category.slug = :category')->setParameter('category', $filters['category']);
        }
        if ('' !== ($filters['tag'] ?? '')) {
            $builder->andWhere('tag.slug = :tag')->setParameter('tag', $filters['tag']);
        }
        if ('' !== ($filters['author'] ?? '')) {
            $builder->andWhere('author.slug = :author')->setParameter('author', $filters['author']);
        }

        $builder->distinct();
        match ($filters['sort'] ?? 'latest') {
            'oldest' => $builder->orderBy('article.publishedAt', 'ASC')->addOrderBy('article.id', 'ASC'),
            'popular' => $builder->orderBy('article.viewCount', 'DESC')->addOrderBy('article.publishedAt', 'DESC'),
            default => $builder->orderBy('article.publishedAt', 'DESC')->addOrderBy('article.id', 'DESC'),
        };

        $query = $builder->getQuery()
            ->setFirstResult(max(0, ($page - 1) * $perPage))
            ->setMaxResults($perPage);
        $paginator = new Paginator($query, fetchJoinCollection: true);
        /** @var list<Article> $items */
        $items = iterator_to_array($paginator->getIterator());
        $total = count($paginator);

        return ['items' => $items, 'total' => $total, 'pages' => max(1, (int) ceil($total / $perPage))];
    }

    public function findOnePublishedBySlug(string $slug): ?Article
    {
        return $this->publishedBuilder()
            ->andWhere('article.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @param list<int> $ids
     * @return list<Article>
     */
    public function findPublishedByIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        $items = $this->publishedBuilder()
            ->andWhere('article.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
        $byId = [];
        foreach ($items as $item) {
            $byId[$item->getId()] = $item;
        }

        $ordered = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $ordered[] = $byId[$id];
            }
        }

        return $ordered;
    }

    /** @return list<Article> */
    public function findRelated(Article $article, int $limit = 3): array
    {
        $builder = $this->publishedBuilder()
            ->andWhere('article.id != :exclude')
            ->setParameter('exclude', $article);

        $conditions = [];
        if (!$article->getCategories()->isEmpty()) {
            $conditions[] = 'category.id IN (:categories)';
            $builder->setParameter('categories', $article->getCategories()->map(static fn ($category): ?int => $category->getId())->toArray());
        }
        if (!$article->getTags()->isEmpty()) {
            $conditions[] = 'tag.id IN (:tags)';
            $builder->setParameter('tags', $article->getTags()->map(static fn ($tag): ?int => $tag->getId())->toArray());
        }
        if ([] === $conditions) {
            return [];
        }

        return $builder->andWhere($builder->expr()->orX(...$conditions))
            ->distinct()
            ->orderBy('article.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findPreviousPublished(Article $article): ?Article
    {
        return $this->publishedBuilder()
            ->andWhere('article.publishedAt < :publishedAt OR (article.publishedAt = :publishedAt AND article.id < :id)')
            ->setParameter('publishedAt', $article->getPublishedAt())
            ->setParameter('id', $article->getId())
            ->orderBy('article.publishedAt', 'DESC')
            ->addOrderBy('article.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findNextPublished(Article $article): ?Article
    {
        return $this->publishedBuilder()
            ->andWhere('article.publishedAt > :publishedAt OR (article.publishedAt = :publishedAt AND article.id > :id)')
            ->setParameter('publishedAt', $article->getPublishedAt())
            ->setParameter('id', $article->getId())
            ->orderBy('article.publishedAt', 'ASC')
            ->addOrderBy('article.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<Article> */
    public function mostViewedPublished(int $limit = 3): array
    {
        return $this->publishedBuilder()
            ->distinct()
            ->orderBy('article.viewCount', 'DESC')
            ->addOrderBy('article.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return list<int> */
    public function searchPublishedIds(string $query): array
    {
        if (mb_strlen(trim($query)) < 3) {
            return [];
        }

        $connection = $this->getEntityManager()->getConnection();
        $sql = "SELECT id FROM article WHERE deleted_at IS NULL AND status = 'published' AND published_at <= UTC_TIMESTAMP() AND MATCH(title, excerpt, content) AGAINST (:query IN NATURAL LANGUAGE MODE)";

        try {
            /** @var list<int> $ids */
            $ids = $connection->fetchFirstColumn($sql, ['query' => $query]);
        } catch (\Doctrine\DBAL\Exception) {
            return [];
        }

        return array_map(intval(...), $ids);
    }

    /** @return array<int, int> */
    public function publishedCountsByCategory(): array
    {
        $rows = $this->createQueryBuilder('article')
            ->select('category.id AS categoryId, COUNT(DISTINCT article.id) AS total')
            ->join('article.categories', 'category')
            ->andWhere('article.status = :published')
            ->andWhere('article.publishedAt <= :now')
            ->andWhere('article.isPremium = false')
            ->setParameter('published', ArticleStatus::Published)
            ->setParameter('now', new \DateTimeImmutable())
            ->groupBy('category.id')
            ->getQuery()
            ->getArrayResult();

        return array_column($rows, 'total', 'categoryId');
    }

    /** @return array<int, int> */
    public function publishedCountsByTag(): array
    {
        $rows = $this->createQueryBuilder('article')
            ->select('tag.id AS tagId, COUNT(DISTINCT article.id) AS total')
            ->join('article.tags', 'tag')
            ->andWhere('article.status = :published')
            ->andWhere('article.publishedAt <= :now')
            ->andWhere('article.isPremium = false')
            ->setParameter('published', ArticleStatus::Published)
            ->setParameter('now', new \DateTimeImmutable())
            ->groupBy('tag.id')
            ->getQuery()
            ->getArrayResult();

        return array_column($rows, 'total', 'tagId');
    }

    /** @return array<int, int> */
    public function publishedCountsByAuthor(): array
    {
        $rows = $this->createQueryBuilder('article')
            ->select('IDENTITY(article.author) AS authorId, COUNT(article.id) AS total')
            ->andWhere('article.status = :published')
            ->andWhere('article.publishedAt <= :now')
            ->andWhere('article.isPremium = false')
            ->setParameter('published', ArticleStatus::Published)
            ->setParameter('now', new \DateTimeImmutable())
            ->groupBy('article.author')
            ->getQuery()
            ->getArrayResult();

        return array_column($rows, 'total', 'authorId');
    }

    /** @return list<Article> */
    public function findForSitemap(): array
    {
        return $this->publishedBuilder()
            ->andWhere('article.visibility = :public')
            ->andWhere('article.seo.noindex = false')
            ->setParameter('public', ArticleVisibility::Public)
            ->distinct()
            ->orderBy('article.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<Article> */
    public function findRecentForDashboard(int $limit = 5): array
    {
        return $this->createQueryBuilder('article')
            ->leftJoin('article.author', 'author')->addSelect('author')
            ->leftJoin('article.featuredImage', 'featuredImage')->addSelect('featuredImage')
            ->orderBy('article.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @param list<int> $ids
     * @return list<Article>
     */
    public function findByIdsForAnalytics(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        return $this->findBy(['id' => $ids]);
    }

    public function sumLikeCount(): int
    {
        return (int) $this->createQueryBuilder('article')
            ->select('COALESCE(SUM(article.likeCount), 0)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
