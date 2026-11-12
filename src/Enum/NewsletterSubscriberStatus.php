<?php

declare(strict_types=1);

namespace App\Enum;

enum NewsletterSubscriberStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Unsubscribed = 'unsubscribed';
    case Bounced = 'bounced';
}
