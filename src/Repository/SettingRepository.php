<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Setting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Setting> */
class SettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Setting::class);
    }

    public function findOneByKey(string $key): ?Setting
    {
        return $this->findOneBy(['key' => $key]);
    }

    /** @return list<array{key: string, value: array<string, mixed>, isSensitive: bool}> */
    public function runtimeValues(): array
    {
        // Scalar hydration bypasses stale managed settings inside long-running campaign batches.
        return $this->createQueryBuilder('setting')->select('setting.key', 'setting.value', 'setting.isSensitive')
            ->getQuery()->getArrayResult();
    }
}
