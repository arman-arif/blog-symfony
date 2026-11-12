<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Redirect;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Redirect> */
class RedirectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Redirect::class);
    }

    public function findOneBySourcePath(string $sourcePath): ?Redirect
    {
        return $this->findOneBy(['sourcePath' => $sourcePath]);
    }

    public function incrementHits(string $sourcePath): void
    {
        $this->createQueryBuilder('redirect')
            ->update()
            ->set('redirect.hits', 'redirect.hits + 1')
            ->andWhere('redirect.sourcePath = :source')
            ->setParameter('source', $sourcePath)
            ->getQuery()
            ->execute();
    }
}
