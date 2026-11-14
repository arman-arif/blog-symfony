<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Entity\Permission;
use App\Entity\Role;
use App\Repository\PermissionRepository;
use App\Repository\RoleRepository;
use App\Security\PermissionCatalog;
use Doctrine\ORM\EntityManagerInterface;

final readonly class PermissionSyncService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PermissionRepository $permissions,
        private RoleRepository $roles,
    ) {
    }

    /** @return array{permissions: int, roles: int} */
    public function sync(): array
    {
        return $this->entityManager->wrapInTransaction(function (): array {
            $created = ['permissions' => 0, 'roles' => 0];
            $permissions = [];
            foreach (PermissionCatalog::permissions() as $code => $definition) {
                $permission = $this->permissions->findOneByCode($code);
                if (null === $permission) {
                    $permission = (new Permission())->setCode($code)->setLabel($definition['label'])->setGroupName($definition['group']);
                    $this->entityManager->persist($permission);
                    ++$created['permissions'];
                }
                $permissions[$code] = $permission;
            }

            foreach (PermissionCatalog::roles() as $code => $definition) {
                // Existing grants (including deliberate revocations) are operator policy.
                if (null !== $this->roles->findOneByCode($code)) {
                    continue;
                }
                $role = (new Role())->setCode($code)->setName($definition['name'])->setDescription($definition['description'])->setIsSystem(true);
                foreach ($definition['permissions'] as $permissionCode) {
                    $role->addPermission($permissions[$permissionCode]);
                }
                $this->entityManager->persist($role);
                ++$created['roles'];
            }

            return $created;
        });
    }
}
