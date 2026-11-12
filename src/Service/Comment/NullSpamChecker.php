<?php

declare(strict_types=1);

namespace App\Service\Comment;

final readonly class NullSpamChecker implements SpamCheckerInterface
{
    public function check(string $content, ?string $authorIp): ?string
    {
        return null;
    }
}
