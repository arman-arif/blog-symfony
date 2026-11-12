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
}
