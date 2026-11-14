<?php

declare(strict_types=1);

namespace App\Enum;

enum NewsletterDeliveryStatus: string
{
    case Pending = 'pending';
    case Sending = 'sending';
    case Sent = 'sent';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case Uncertain = 'uncertain';
}
