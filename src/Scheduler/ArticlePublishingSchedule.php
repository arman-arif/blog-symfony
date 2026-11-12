<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Message\AggregateAnalyticsMessage;
use App\Message\PublishScheduledArticlesMessage;
use App\Message\SendScheduledNewsletterCampaignsMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule('default')]
final readonly class ArticlePublishingSchedule implements ScheduleProviderInterface
{
    public function __construct(private CacheInterface $cache)
    {
    }

    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true)
            ->add(RecurringMessage::every('1 minute', new PublishScheduledArticlesMessage()))
            ->add(RecurringMessage::every('1 minute', new SendScheduledNewsletterCampaignsMessage()))
            ->add(RecurringMessage::every('1 hour', new AggregateAnalyticsMessage()));
    }
}
