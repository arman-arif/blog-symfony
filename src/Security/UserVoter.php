<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/** User managers may only manage accounts with assignable, non-administrative roles. */
/** @extends Voter<string, mixed> */
final class UserVoter extends Voter
{
    public const string MANAGE = 'USER_MANAGE';

    public function __construct(private readonly RoleAssignmentPolicy $rolePolicy)
    {
    }

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
        if (!$user instanceof User) {
            return false;
        }

        foreach ($subject->getAssignedRoles() as $role) {
            if (!$this->rolePolicy->canAssign($user, $role)) {
                return false;
            }
        }

        foreach ($user->getAssignedRoles() as $role) {
            if ($role->hasPermission('user.manage')) {
                return true;
            }
        }

        return false;
    }
}
