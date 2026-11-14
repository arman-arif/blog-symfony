<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ArticleDailyStat;
use App\Enum\ArticleStatus;
use App\Enum\ArticleVisibility;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ArticleDailyStat> */
class ArticleDailyStatRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ArticleDailyStat::class);
    }

    public function findOneForDay(int $articleId, \DateTimeImmutable $day): ?ArticleDailyStat
    {
        return $this->findOneBy(['article' => $articleId, 'statDate' => $day->setTime(0, 0)]);
    }

    /** @return list<int> Public popularity over the last 30 calendar days, independent of raw-view retention. */
    public function popularPublicIds(\DateTimeImmutable $now, int $limit = 3): array
    {
        if ($limit < 1) {
            return [];
        }
        $rows = $this->scopedRange($now->modify('-29 days'), $now, null)
            ->select('article.id AS id, SUM(stat.views) AS total')
            ->andWhere('article.deletedAt IS NULL AND article.status = :published AND article.visibility = :public')
            ->andWhere('article.isPremium = false AND article.publishedAt <= :now')
            ->setParameter('published', ArticleStatus::Published)->setParameter('public', ArticleVisibility::Public)->setParameter('now', $now)
            ->groupBy('article.id')->having('SUM(stat.views) > 0')
            ->orderBy('total', 'DESC')->addOrderBy('article.id', 'DESC')
            ->setMaxResults($limit)->getQuery()->getScalarResult();

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }

    /** @return array<string, int> Date (Y-m-d) => total views. */
    public function dailyTotals(\DateTimeImmutable $from, \DateTimeImmutable $to, ?int $authorId = null): array
    {
        $rows = $this->scopedRange($from, $to, $authorId)
            ->select('stat.statDate AS day, SUM(stat.views) AS total')
            ->groupBy('stat.statDate')
            ->orderBy('stat.statDate', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $totals = [];
        foreach ($rows as $row) {
            $day = $row['day'] instanceof \DateTimeInterface ? $row['day']->format('Y-m-d') : substr((string) $row['day'], 0, 10);
            $totals[$day] = (int) $row['total'];
        }

        return $totals;
    }

    public function sumViews(\DateTimeImmutable $from, \DateTimeImmutable $to, ?int $authorId = null): int
    {
        return (int) $this->scopedRange($from, $to, $authorId)
            ->select('COALESCE(SUM(stat.views), 0)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<array{articleId: int, views: int}> */
    public function topArticles(\DateTimeImmutable $from, \DateTimeImmutable $to, int $limit = 10, ?int $authorId = null): array
    {
        $rows = $this->scopedRange($from, $to, $authorId)
            ->select('IDENTITY(stat.article) AS articleId, SUM(stat.views) AS views')
            ->groupBy('stat.article')
            ->orderBy('views', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): array => ['articleId' => (int) $row['articleId'], 'views' => (int) $row['views']], $rows);
    }

    /** @return list<array{id: int, name: string, views: int}> */
    public function popularCategories(\DateTimeImmutable $from, \DateTimeImmutable $to, int $limit = 6, ?int $authorId = null): array
    {
        $rows = $this->scopedRange($from, $to, $authorId)
            ->select('category.id AS id, category.name AS name, SUM(stat.views) AS views')
            ->join('article.categories', 'category')
            ->groupBy('category.id')
            ->addGroupBy('category.name')
            ->orderBy('views', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): array => ['id' => (int) $row['id'], 'name' => (string) $row['name'], 'views' => (int) $row['views']], $rows);
    }

    /** @return list<array{id: int, name: string, views: int}> */
    public function popularTags(\DateTimeImmutable $from, \DateTimeImmutable $to, int $limit = 6, ?int $authorId = null): array
    {
        $rows = $this->scopedRange($from, $to, $authorId)
            ->select('tag.id AS id, tag.name AS name, SUM(stat.views) AS views')
            ->join('article.tags', 'tag')
            ->groupBy('tag.id')
            ->addGroupBy('tag.name')
            ->orderBy('views', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): array => ['id' => (int) $row['id'], 'name' => (string) $row['name'], 'views' => (int) $row['views']], $rows);
    }

    /** @return list<array{id: int, name: string, slug: string, views: int}> */
    public function authorPerformance(\DateTimeImmutable $from, \DateTimeImmutable $to, int $limit = 6, ?int $authorId = null): array
    {
        $rows = $this->scopedRange($from, $to, $authorId)
            ->select('author.id AS id, author.displayName AS name, author.slug AS slug, SUM(stat.views) AS views')
            ->join('article.author', 'author')
            ->groupBy('author.id')
            ->addGroupBy('author.displayName')
            ->addGroupBy('author.slug')
            ->orderBy('views', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): array => ['id' => (int) $row['id'], 'name' => (string) $row['name'], 'slug' => (string) $row['slug'], 'views' => (int) $row['views']], $rows);
    }

    private function scopedRange(\DateTimeImmutable $from, \DateTimeImmutable $to, ?int $authorId): QueryBuilder
    {
        $builder = $this->createQueryBuilder('stat')
            ->join('stat.article', 'article')
            ->andWhere('stat.statDate >= :from')->andWhere('stat.statDate <= :to')
            ->setParameter('from', $from->setTime(0, 0))->setParameter('to', $to->setTime(0, 0));
        if (null !== $authorId) {
            $builder->andWhere('article.author = :scopeAuthor')->setParameter('scopeAuthor', $authorId);
        }

        return $builder;
    }
}
