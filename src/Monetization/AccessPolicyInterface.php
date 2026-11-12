<?php

declare(strict_types=1);

namespace App\Monetization;

use App\Entity\Article;
use App\Entity\User;

/** Extension point for premium/member access policies. */
interface AccessPolicyInterface
{
    public function canAccess(Article $article, ?User $user): bool;
}
