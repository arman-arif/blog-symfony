<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function findOneByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    public function findOneBySlug(string $slug): ?User
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    /** @param array{q?: string, role?: string, active?: string} $filters
     * @return array{items: list<User>, total: int, pages: int}
     */
    public function paginateAdmin(array $filters, int $page, int $perPage = 20): array
    {
        $builder = $this->createQueryBuilder('user')
            ->leftJoin('user.assignedRoles', 'role')->addSelect('role')
            ->distinct();

        if ('' !== ($filters['q'] ?? '')) {
            $builder->andWhere('user.displayName LIKE :q OR user.email LIKE :q')
                ->setParameter('q', '%'.$filters['q'].'%');
        }
        if ('' !== ($filters['role'] ?? '')) {
            $builder->andWhere('role.code = :role')->setParameter('role', $filters['role']);
        }
        if ('' !== ($filters['active'] ?? '')) {
            $builder->andWhere('user.isActive = :active')->setParameter('active', '1' === $filters['active']);
        }

        $total = (int) (clone $builder)->select('COUNT(DISTINCT user.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();
        $items = $builder->orderBy('user.displayName', 'ASC')->setFirstResult(max(0, ($page - 1) * $perPage))->setMaxResults($perPage)->getQuery()->getResult();

        return ['items' => $items, 'total' => $total, 'pages' => max(1, (int) ceil($total / $perPage))];
    }

    public function countSuperAdmins(): int
    {
        return (int) $this->createQueryBuilder('user')
            ->select('COUNT(DISTINCT user.id)')
            ->join('user.assignedRoles', 'role')
            ->andWhere('role.code = :code')
            ->setParameter('code', 'ROLE_SUPER_ADMIN')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
