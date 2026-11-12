<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\NewsletterSubscriber;
use App\Enum\NewsletterSubscriberStatus;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/** @extends PersistentObjectFactory<NewsletterSubscriber> */
final class NewsletterSubscriberFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return NewsletterSubscriber::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'email' => self::faker()->unique()->safeEmail(),
            'token' => bin2hex(random_bytes(16)),
            'status' => NewsletterSubscriberStatus::Pending,
        ];
    }
}
