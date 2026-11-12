<?php

declare(strict_types=1);

namespace App\Service\Comment;

interface SpamCheckerInterface
{
    /** Return a rejection reason, or null when the comment is acceptable. */
    public function check(string $content, ?string $authorIp): ?string;
}
