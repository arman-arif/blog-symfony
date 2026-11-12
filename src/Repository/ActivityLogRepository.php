<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ActivityLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ActivityLog> */
class ActivityLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityLog::class);
    }

    /** @return list<ActivityLog> */
    public function findRecent(int $limit = 5): array
    {
        return $this->createQueryBuilder('log')
            ->leftJoin('log.user', 'user')->addSelect('user')
            ->orderBy('log.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @param array{q?: string, action?: string} $filters
     * @return array{items: list<ActivityLog>, total: int, pages: int}
     */
    public function paginate(array $filters, int $page, int $perPage = 25): array
    {
        $builder = $this->createQueryBuilder('log')
            ->leftJoin('log.user', 'user')->addSelect('user');
        if ('' !== ($filters['q'] ?? '')) {
            $builder->andWhere('log.action LIKE :q OR user.displayName LIKE :q OR log.subjectType LIKE :q')
                ->setParameter('q', '%'.$filters['q'].'%');
        }
        if ('' !== ($filters['action'] ?? '')) {
            $builder->andWhere('log.action = :action')->setParameter('action', $filters['action']);
        }
        $total = (int) (clone $builder)->select('COUNT(log.id)')->getQuery()->getSingleScalarResult();
        $items = $builder->orderBy('log.createdAt', 'DESC')->setFirstResult(max(0, ($page - 1) * $perPage))->setMaxResults($perPage)->getQuery()->getResult();

        return ['items' => $items, 'total' => $total, 'pages' => max(1, (int) ceil($total / $perPage))];
    }

    /** @return list<string> */
    public function distinctActions(): array
    {
        return array_map(strval(...), array_column($this->createQueryBuilder('log')->select('DISTINCT log.action AS action')->orderBy('log.action', 'ASC')->getQuery()->getArrayResult(), 'action'));
    }
}
