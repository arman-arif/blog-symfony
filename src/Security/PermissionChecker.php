<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;

final readonly class PermissionChecker
{
    public function can(User $user, string $permission): bool
    {
        if ($user->hasRoleCode('ROLE_SUPER_ADMIN')) {
            return true;
        }

        foreach ($user->getAssignedRoles() as $role) {
            if ($role->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    public function canEditArticle(User $user, \App\Entity\Article $article): bool
    {
        return $this->can($user, 'article.edit.any')
            || ($article->getAuthor()->getId() === $user->getId() && $this->can($user, 'article.edit.own'));
    }

    public function canDeleteArticle(User $user, \App\Entity\Article $article): bool
    {
        return $this->can($user, 'article.delete.any')
            || ($article->getAuthor()->getId() === $user->getId() && $this->can($user, 'article.delete.own'));
    }
}
