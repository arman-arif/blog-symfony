<?php

declare(strict_types=1);

namespace App\Message;

final readonly class RecordArticleViewMessage
{
    public function __construct(
        public int $articleId,
        public string $visitorHash,
        public ?string $referrer = null,
        public ?string $userAgent = null,
    ) {
    }
}
