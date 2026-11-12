<?php

declare(strict_types=1);

namespace App\Service\Comment;

final class CommentSubmission
{
    public string $content = '';
    public ?string $guestName = null;
    public ?string $guestEmail = null;
    public ?int $parentId = null;
    public int $startedAt = 0;
    public ?string $website = null;
}
