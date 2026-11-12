<?php

declare(strict_types=1);

namespace App\Enum;

enum ArticleStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Unpublished = 'unpublished';
}
