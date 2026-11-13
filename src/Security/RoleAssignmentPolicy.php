<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Role;
use App\Entity\User;

/** User managers cannot grant administrative roles or capabilities they do not hold. */
final readonly class RoleAssignmentPolicy
{
    public function canAssign(User $actor, Role $role): bool
    {
        if ($actor->hasRoleCode('ROLE_SUPER_ADMIN')) {
            return true;
        }
        if (in_array($role->getCode(), ['ROLE_SUPER_ADMIN', 'ROLE_ADMIN'], true)) {
            return false;
        }
        $permissions = [];
        foreach ($actor->getAssignedRoles() as $assigned) {
            foreach ($assigned->getPermissions() as $permission) {
                $permissions[$permission->getCode()] = true;
            }
        }
        if (!isset($permissions['user.manage'])) {
            return false;
        }
        foreach ($role->getPermissions() as $permission) {
            if (!isset($permissions[$permission->getCode()])) {
                return false;
            }
        }

        return true;
    }
}
