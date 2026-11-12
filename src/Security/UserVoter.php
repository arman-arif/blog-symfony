<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/** Administrators with user.manage may not manage super administrators. */
/** @extends Voter<string, mixed> */
final class UserVoter extends Voter
{
    public const string MANAGE = 'USER_MANAGE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::MANAGE === $attribute && $subject instanceof User;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        \assert($subject instanceof User);
        if (in_array('ROLE_SUPER_ADMIN', $token->getRoleNames(), true)) {
            return true;
        }

        $user = $token->getUser();
        if (!$user instanceof User || $subject->hasRoleCode('ROLE_SUPER_ADMIN')) {
            return false;
        }

        foreach ($user->getAssignedRoles() as $role) {
            if ($role->hasPermission('user.manage')) {
                return true;
            }
        }

        return false;
    }
}
