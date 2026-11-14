<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Entity\Role;
use App\Entity\User;
use App\Repository\RoleRepository;
use App\Repository\UserRepository;
use App\Security\RoleAssignmentPolicy;
use App\Validator\StrongPassword;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class UserManagementService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $users,
        private RoleRepository $roles,
        private RoleAssignmentPolicy $rolePolicy,
        private UserPasswordHasherInterface $passwordHasher,
        private ValidatorInterface $validator,
    ) {
    }

    /** @param list<Role> $assignedRoles */
    public function save(User $user, User $actor, array $assignedRoles, string $plainPassword, bool $creating, ?User $details = null): void
    {
        $details ??= $user;
        if (!$actor->hasRoleCode('ROLE_SUPER_ADMIN')) {
            if (!array_any($actor->getAssignedRoles()->toArray(), static fn (Role $role): bool => $role->hasPermission('user.manage'))) {
                throw new AccessDeniedException('You cannot manage users.');
            }
            foreach ($user->getAssignedRoles() as $role) {
                if (!$this->rolePolicy->canAssign($actor, $role)) {
                    throw new AccessDeniedException('You cannot manage this account.');
                }
            }
        }
        foreach ($assignedRoles as $role) {
            if (!$this->rolePolicy->canAssign($actor, $role)) {
                throw new AccessDeniedException('You cannot assign this role.');
            }
        }
        if ($creating || '' !== $plainPassword) {
            // Keep a scalar password out of the violation root/exception message.
            $violations = $this->validator->validate(['password' => $plainPassword], new Assert\Collection([
                'password' => [new Assert\NotBlank(), new StrongPassword()],
            ]));
            if (count($violations) > 0) {
                throw new ValidationFailedException(null, $violations);
            }
        }
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();
        try {
            // Serialize changes to Super Admin membership across all application hosts.
            $superRole = $this->roles->findOneByCode('ROLE_SUPER_ADMIN');
            if (null !== $superRole) {
                $this->entityManager->lock($superRole, LockMode::PESSIMISTIC_WRITE);
            }
            $remainsActiveSuper = $details->isActive() && null === $user->getDeletedAt()
                && array_any($assignedRoles, static fn (Role $role): bool => 'ROLE_SUPER_ADMIN' === $role->getCode());
            if (!$creating && $user->hasRoleCode('ROLE_SUPER_ADMIN') && !$remainsActiveSuper
                && 0 === $this->users->countSuperAdmins($user->getId(), forUpdate: true)) {
                throw new \DomainException('The last Super Administrator cannot be demoted or deactivated.');
            }
            $user->setDisplayName($details->getDisplayName())->setEmail($details->getEmail())
                ->setJobTitle($details->getJobTitle())->setBio($details->getBio())
                ->setIsActive($details->isActive());
            if ('' !== $plainPassword) {
                $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
            }
            $user->getAssignedRoles()->clear();
            foreach ($assignedRoles as $role) {
                $user->addAssignedRole($role);
            }
            if ($creating) {
                $user->setIsVerified(true);
            }
            $this->entityManager->persist($user);
            $this->entityManager->flush();
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            // Form binding changed a managed entity; do not leave rejected changes dirty.
            if (!$creating && $this->entityManager->isOpen()) {
                $this->entityManager->refresh($user);
            }
            throw $exception;
        }
    }
}
