<?php

declare(strict_types=1);

namespace App\Monetization;

use App\Entity\Article;
use App\Entity\User;

/** Placeholder policy: premium articles stay hidden until a membership policy is implemented. */
final readonly class DefaultAccessPolicy implements AccessPolicyInterface
{
    public function canAccess(Article $article, ?User $user): bool
    {
        return !$article->isPremium();
    }
}
