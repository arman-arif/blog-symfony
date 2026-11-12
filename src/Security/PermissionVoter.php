<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/** Checks granular permission codes such as `article.publish` against the user's assigned roles. */
/** @extends Voter<string, mixed> */
final class PermissionVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return 1 === preg_match('/^[a-z][a-z0-9_]*(\.[a-z0-9_]+)+$/', $attribute);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        if (in_array('ROLE_SUPER_ADMIN', $token->getRoleNames(), true)) {
            return true;
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        foreach ($user->getAssignedRoles() as $role) {
            if ($role->hasPermission($attribute)) {
                return true;
            }
        }

        return false;
    }
}
