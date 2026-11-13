<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Article;
use App\Entity\User;
use App\Enum\ArticleStatus;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/** Own-versus-any article access, kept separate from generic permission codes. */
/** @extends Voter<string, mixed> */
final class ArticleVoter extends Voter
{
    public const string EDIT = 'ARTICLE_EDIT';
    public const string DELETE = 'ARTICLE_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::EDIT, self::DELETE], true) && $subject instanceof Article;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        \assert($subject instanceof Article);
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        if (in_array('ROLE_SUPER_ADMIN', $token->getRoleNames(), true)) {
            return true;
        }

        $permission = self::EDIT === $attribute ? 'article.edit' : 'article.delete';

        foreach ($user->getAssignedRoles() as $role) {
            if ($role->hasPermission($permission.'.any')) {
                return true;
            }
        }
        foreach ($user->getAssignedRoles() as $role) {
            if ($role->hasPermission($permission.'.own') && $subject->getAuthor()->getId() === $user->getId()
                && ('ROLE_CONTRIBUTOR' !== $role->getCode() || ArticleStatus::Draft === $subject->getStatus())) {
                return true;
            }
        }

        return false;
    }
}
