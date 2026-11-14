<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Advertisement;
use App\Enum\AdvertisementPlacement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Advertisement> */
class AdvertisementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Advertisement::class);
    }

    /** @return list<int> Schedule-independent candidates, so a cached miss cannot hide a start boundary. */
    public function enabledIdsForPlacement(AdvertisementPlacement $placement): array
    {
        $rows = $this->createQueryBuilder('ad')->select('ad.id')
            ->andWhere('ad.isActive = true AND ad.placement = :placement')->setParameter('placement', $placement)
            ->orderBy('ad.priority', 'DESC')->addOrderBy('ad.id', 'DESC')->getQuery()->getScalarResult();

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }

    /** @return list<Advertisement> */
    public function findActiveForPlacement(AdvertisementPlacement $placement, ?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();

        return $this->createQueryBuilder('ad')
            ->leftJoin('ad.image', 'image')->addSelect('image')
            ->andWhere('ad.isActive = true')
            ->andWhere('ad.placement = :placement')
            ->andWhere('ad.startsAt IS NULL OR ad.startsAt <= :now')
            ->andWhere('ad.endsAt IS NULL OR ad.endsAt > :now')
            ->setParameter('placement', $placement)
            ->setParameter('now', $now)
            ->orderBy('ad.priority', 'DESC')
            ->addOrderBy('ad.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
