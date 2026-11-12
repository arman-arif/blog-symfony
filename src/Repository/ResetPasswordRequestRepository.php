<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ResetPasswordRequest;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordRequestInterface;
use SymfonyCasts\Bundle\ResetPassword\Persistence\ResetPasswordRequestRepositoryInterface;

/**
 * @extends ServiceEntityRepository<ResetPasswordRequest>
 */
class ResetPasswordRequestRepository extends ServiceEntityRepository implements ResetPasswordRequestRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ResetPasswordRequest::class);
    }

    public function createResetPasswordRequest(object $user, \DateTimeInterface $expiresAt, string $selector, string $hashedToken): ResetPasswordRequestInterface
    {
        \assert($user instanceof User);

        return new ResetPasswordRequest($user, $expiresAt, $selector, $hashedToken);
    }

    public function getUserIdentifier(object $user): string
    {
        \assert($user instanceof User);

        return (string) $user->getId();
    }

    public function persistResetPasswordRequest(ResetPasswordRequestInterface $resetPasswordRequest): void
    {
        $this->getEntityManager()->persist($resetPasswordRequest);
        $this->getEntityManager()->flush();
    }

    public function findResetPasswordRequest(string $selector): ?ResetPasswordRequestInterface
    {
        return $this->findOneBy(['selector' => $selector]);
    }

    public function getMostRecentNonExpiredRequestDate(object $user): ?\DateTimeInterface
    {
        \assert($user instanceof User);

        $request = $this->createQueryBuilder('request')
            ->andWhere('request.user = :user')
            ->andWhere('request.expiresAt > :now')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('request.requestedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $request?->getRequestedAt();
    }

    public function removeResetPasswordRequest(ResetPasswordRequestInterface $resetPasswordRequest): void
    {
        $this->createQueryBuilder('request')
            ->delete()
            ->andWhere('request.user = :user')
            ->setParameter('user', $resetPasswordRequest->getUser())
            ->getQuery()
            ->execute();
    }

    public function removeExpiredResetPasswordRequests(): int
    {
        return (int) $this->createQueryBuilder('request')
            ->delete()
            ->andWhere('request.expiresAt <= :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }
}
