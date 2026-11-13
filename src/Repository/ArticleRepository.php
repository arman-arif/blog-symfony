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
        return $this->limitedPublished($this->publishedBuilder()->orderBy('article.publishedAt', 'DESC')->addOrderBy('article.id', 'DESC'), $limit);
    }

    /**
     * @param array{q?: string, status?: string, author?: int, category?: int} $filters
     *
     * @return array{items: list<Article>, total: int}
     */
    public function findForAdmin(array $filters, int $page, int $perPage, bool $includeTrashed = false, ?int $restrictToAuthorId = null): array
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
            if (null !== $restrictToAuthorId) {
                $builder->andWhere('author.id = :scopeAuthor')->setParameter('scopeAuthor', $restrictToAuthorId);
            }

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
                $builder->join('article.categories', 'filterCategory')
                    ->andWhere('filterCategory.id = :category')->setParameter('category', $filters['category']);
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

    public function countByStatus(ArticleStatus $status, ?int $authorId = null): int
    {
        $builder = $this->createQueryBuilder('article')
            ->select('COUNT(article.id)')
            ->andWhere('article.status = :status')
            ->setParameter('status', $status);
        if (null !== $authorId) {
            $builder->andWhere('article.author = :scopeAuthor')->setParameter('scopeAuthor', $authorId);
        }

        return (int) $builder->getQuery()->getSingleScalarResult();
    }

    private function publishedCriteriaBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('article')
            ->andWhere('article.deletedAt IS NULL')
            ->andWhere('article.status = :published')
            ->andWhere('article.visibility = :public')
            ->andWhere('article.publishedAt IS NOT NULL')
            ->andWhere('article.publishedAt <= :now')
            ->andWhere('article.isPremium = false')
            ->setParameter('published', ArticleStatus::Published)
            ->setParameter('public', ArticleVisibility::Public)
            ->setParameter('now', new \DateTimeImmutable());
    }

    private function publishedBuilder(): QueryBuilder
    {
        return $this->publishedCriteriaBuilder()
            ->leftJoin('article.author', 'author')->addSelect('author')
            ->leftJoin('author.avatar', 'avatar')->addSelect('avatar')
            ->leftJoin('article.categories', 'category')->addSelect('category')
            ->leftJoin('article.tags', 'tag')->addSelect('tag')
            ->leftJoin('article.featuredImage', 'featuredImage')->addSelect('featuredImage');
    }

    /** @return list<Article> */
    private function limitedPublished(QueryBuilder $builder, int $limit): array
    {
        if ($limit < 1) {
            return [];
        }
        $paginator = new Paginator($builder->getQuery()->setMaxResults($limit), fetchJoinCollection: true);

        return array_values(iterator_to_array($paginator->getIterator()));
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
            $builder->join('article.categories', 'filterCategory')
                ->andWhere('filterCategory.slug = :category')->setParameter('category', $filters['category']);
        }
        if ('' !== ($filters['tag'] ?? '')) {
            $builder->join('article.tags', 'filterTag')
                ->andWhere('filterTag.slug = :tag')->setParameter('tag', $filters['tag']);
        }
        if ('' !== ($filters['author'] ?? '')) {
            $builder->andWhere('author.slug = :author')->setParameter('author', $filters['author']);
        }

        $builder->distinct();
        match ($filters['sort'] ?? 'latest') {
            'oldest' => $builder->orderBy('article.publishedAt', 'ASC')->addOrderBy('article.id', 'ASC'),
            'popular' => $builder->orderBy('article.viewCount', 'DESC')->addOrderBy('article.publishedAt', 'DESC')->addOrderBy('article.id', 'DESC'),
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
        $builder = $this->publishedCriteriaBuilder()->select('article.id')
            ->andWhere('article.id != :exclude')
            ->setParameter('exclude', $article->getId());

        $conditions = [];
        $scores = [];
        if (!$article->getCategories()->isEmpty()) {
            $builder->leftJoin('article.categories', 'relatedCategory', 'WITH', 'relatedCategory.id IN (:categories)');
            $conditions[] = 'relatedCategory.id IS NOT NULL';
            $scores[] = 'COUNT(DISTINCT relatedCategory.id)';
            $builder->setParameter('categories', $article->getCategories()->map(static fn ($category): ?int => $category->getId())->toArray());
        }
        if (!$article->getTags()->isEmpty()) {
            $builder->leftJoin('article.tags', 'relatedTag', 'WITH', 'relatedTag.id IN (:tags)');
            $conditions[] = 'relatedTag.id IS NOT NULL';
            $scores[] = 'COUNT(DISTINCT relatedTag.id)';
            $builder->setParameter('tags', $article->getTags()->map(static fn ($tag): ?int => $tag->getId())->toArray());
        }
        if ([] === $conditions || $limit < 1) {
            return [];
        }

        $rows = $builder->andWhere($builder->expr()->orX(...$conditions))
            ->addSelect(implode(' + ', $scores).' AS HIDDEN matchScore')
            ->groupBy('article.id')->addGroupBy('article.publishedAt')
            ->orderBy('matchScore', 'DESC')->addOrderBy('article.publishedAt', 'DESC')->addOrderBy('article.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getScalarResult();

        // Limit distinct article IDs first; hydrate complete, unfiltered collections second.
        return $this->findPublishedByIds(array_map(static fn (array $row): int => (int) $row['id'], $rows));
    }

    public function findPreviousPublished(Article $article): ?Article
    {
        $rows = $this->publishedCriteriaBuilder()->select('article.id')
            ->andWhere('article.publishedAt < :publishedAt OR (article.publishedAt = :publishedAt AND article.id < :id)')
            ->setParameter('publishedAt', $article->getPublishedAt())
            ->setParameter('id', $article->getId())
            ->orderBy('article.publishedAt', 'DESC')
            ->addOrderBy('article.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getScalarResult();

        return [] === $rows ? null : ($this->findPublishedByIds([(int) $rows[0]['id']])[0] ?? null);
    }

    public function findNextPublished(Article $article): ?Article
    {
        $rows = $this->publishedCriteriaBuilder()->select('article.id')
            ->andWhere('article.publishedAt > :publishedAt OR (article.publishedAt = :publishedAt AND article.id > :id)')
            ->setParameter('publishedAt', $article->getPublishedAt())
            ->setParameter('id', $article->getId())
            ->orderBy('article.publishedAt', 'ASC')
            ->addOrderBy('article.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getScalarResult();

        return [] === $rows ? null : ($this->findPublishedByIds([(int) $rows[0]['id']])[0] ?? null);
    }

    /** @return list<Article> */
    public function mostViewedPublished(int $limit = 3): array
    {
        return $this->limitedPublished($this->publishedBuilder()
            ->orderBy('article.viewCount', 'DESC')
            ->addOrderBy('article.publishedAt', 'DESC')
            ->addOrderBy('article.id', 'DESC'), $limit);
    }

    /** @return list<int> */
    public function searchPublishedIds(string $query): array
    {
        if (mb_strlen(trim($query)) < 3) {
            return [];
        }

        $connection = $this->getEntityManager()->getConnection();
        $sql = "SELECT id FROM article WHERE deleted_at IS NULL AND status = 'published' AND visibility = 'public' AND is_premium = 0 AND published_at <= UTC_TIMESTAMP() AND MATCH(title, excerpt, content) AGAINST (:query IN NATURAL LANGUAGE MODE) ORDER BY MATCH(title, excerpt, content) AGAINST (:query IN NATURAL LANGUAGE MODE) DESC, id DESC";

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
        $rows = $this->publishedCriteriaBuilder()
            ->select('category.id AS categoryId, COUNT(DISTINCT article.id) AS total')
            ->join('article.categories', 'category')
            ->groupBy('category.id')
            ->getQuery()
            ->getArrayResult();

        return array_column($rows, 'total', 'categoryId');
    }

    /** @return array<int, int> */
    public function publishedCountsByTag(): array
    {
        $rows = $this->publishedCriteriaBuilder()
            ->select('tag.id AS tagId, COUNT(DISTINCT article.id) AS total')
            ->join('article.tags', 'tag')
            ->groupBy('tag.id')
            ->getQuery()
            ->getArrayResult();

        return array_column($rows, 'total', 'tagId');
    }

    /** @return array<int, int> */
    public function publishedCountsByAuthor(): array
    {
        $rows = $this->publishedCriteriaBuilder()
            ->select('IDENTITY(article.author) AS authorId, COUNT(article.id) AS total')
            ->groupBy('article.author')
            ->getQuery()
            ->getArrayResult();

        return array_column($rows, 'total', 'authorId');
    }

    /** @return list<Article> */
    public function findForSitemap(): array
    {
        return $this->publishedBuilder()
            ->andWhere('article.seo.noindex = false')
            ->distinct()
            ->orderBy('article.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<Article> */
    public function findRecentForDashboard(int $limit = 5, ?int $authorId = null): array
    {
        $builder = $this->createQueryBuilder('article')
            ->leftJoin('article.author', 'author')->addSelect('author')
            ->leftJoin('article.featuredImage', 'featuredImage')->addSelect('featuredImage')
            ->orderBy('article.updatedAt', 'DESC')
            ->addOrderBy('article.id', 'DESC')
            ->setMaxResults($limit);
        if (null !== $authorId) {
            $builder->andWhere('author.id = :scopeAuthor')->setParameter('scopeAuthor', $authorId);
        }

        return $builder->getQuery()->getResult();
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

    public function sumLikeCount(?int $authorId = null): int
    {
        $builder = $this->createQueryBuilder('article')->select('COALESCE(SUM(article.likeCount), 0)');
        if (null !== $authorId) {
            $builder->andWhere('article.author = :scopeAuthor')->setParameter('scopeAuthor', $authorId);
        }

        return (int) $builder->getQuery()->getSingleScalarResult();
    }
}
