<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ArticleDailyStat;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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

    /** @return array<string, int> Date (Y-m-d) => total views. */
    public function dailyTotals(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $rows = $this->createQueryBuilder('stat')
            ->select('stat.statDate AS day, SUM(stat.views) AS total')
            ->andWhere('stat.statDate >= :from')
            ->andWhere('stat.statDate <= :to')
            ->setParameter('from', $from->setTime(0, 0))
            ->setParameter('to', $to->setTime(0, 0))
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

    public function sumViews(\DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        return (int) $this->createQueryBuilder('stat')
            ->select('COALESCE(SUM(stat.views), 0)')
            ->andWhere('stat.statDate >= :from')
            ->andWhere('stat.statDate <= :to')
            ->setParameter('from', $from->setTime(0, 0))
            ->setParameter('to', $to->setTime(0, 0))
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<array{articleId: int, views: int}> */
    public function topArticles(\DateTimeImmutable $from, \DateTimeImmutable $to, int $limit = 10): array
    {
        $rows = $this->createQueryBuilder('stat')
            ->select('IDENTITY(stat.article) AS articleId, SUM(stat.views) AS views')
            ->andWhere('stat.statDate >= :from')
            ->andWhere('stat.statDate <= :to')
            ->setParameter('from', $from->setTime(0, 0))
            ->setParameter('to', $to->setTime(0, 0))
            ->groupBy('stat.article')
            ->orderBy('views', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): array => ['articleId' => (int) $row['articleId'], 'views' => (int) $row['views']], $rows);
    }

    /** @return list<array{id: int, name: string, views: int}> */
    public function popularCategories(\DateTimeImmutable $from, \DateTimeImmutable $to, int $limit = 6): array
    {
        $rows = $this->createQueryBuilder('stat')
            ->select('category.id AS id, category.name AS name, SUM(stat.views) AS views')
            ->join('stat.article', 'article')
            ->join('article.categories', 'category')
            ->andWhere('stat.statDate >= :from')
            ->andWhere('stat.statDate <= :to')
            ->setParameter('from', $from->setTime(0, 0))
            ->setParameter('to', $to->setTime(0, 0))
            ->groupBy('category.id')
            ->addGroupBy('category.name')
            ->orderBy('views', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): array => ['id' => (int) $row['id'], 'name' => (string) $row['name'], 'views' => (int) $row['views']], $rows);
    }

    /** @return list<array{id: int, name: string, views: int}> */
    public function popularTags(\DateTimeImmutable $from, \DateTimeImmutable $to, int $limit = 6): array
    {
        $rows = $this->createQueryBuilder('stat')
            ->select('tag.id AS id, tag.name AS name, SUM(stat.views) AS views')
            ->join('stat.article', 'article')
            ->join('article.tags', 'tag')
            ->andWhere('stat.statDate >= :from')
            ->andWhere('stat.statDate <= :to')
            ->setParameter('from', $from->setTime(0, 0))
            ->setParameter('to', $to->setTime(0, 0))
            ->groupBy('tag.id')
            ->addGroupBy('tag.name')
            ->orderBy('views', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): array => ['id' => (int) $row['id'], 'name' => (string) $row['name'], 'views' => (int) $row['views']], $rows);
    }

    /** @return list<array{id: int, name: string, slug: string, views: int}> */
    public function authorPerformance(\DateTimeImmutable $from, \DateTimeImmutable $to, int $limit = 6): array
    {
        $rows = $this->createQueryBuilder('stat')
            ->select('author.id AS id, author.displayName AS name, author.slug AS slug, SUM(stat.views) AS views')
            ->join('stat.article', 'article')
            ->join('article.author', 'author')
            ->andWhere('stat.statDate >= :from')
            ->andWhere('stat.statDate <= :to')
            ->setParameter('from', $from->setTime(0, 0))
            ->setParameter('to', $to->setTime(0, 0))
            ->groupBy('author.id')
            ->addGroupBy('author.displayName')
            ->addGroupBy('author.slug')
            ->orderBy('views', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): array => ['id' => (int) $row['id'], 'name' => (string) $row['name'], 'slug' => (string) $row['slug'], 'views' => (int) $row['views']], $rows);
    }
}
