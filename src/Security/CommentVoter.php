<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Comment;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/** Moderators may act on any article; author-role users only on their own article's comments. */
/** @extends Voter<string, mixed> */
final class CommentVoter extends Voter
{
    public const string MODERATE = 'COMMENT_MODERATE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::MODERATE === $attribute && $subject instanceof Comment;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        \assert($subject instanceof Comment);
        if (in_array('ROLE_SUPER_ADMIN', $token->getRoleNames(), true)) {
            return true;
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $canModerate = false;
        foreach ($user->getAssignedRoles() as $role) {
            if (!$role->hasPermission('comment.moderate')) {
                continue;
            }
            $canModerate = true;
            if ($role->hasPermission('article.edit.any')) {
                return true;
            }
        }

        return $canModerate && $subject->getArticle()->getAuthor()->getId() === $user->getId();
    }
}
