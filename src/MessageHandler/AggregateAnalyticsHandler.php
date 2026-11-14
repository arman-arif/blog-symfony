<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\AggregateAnalyticsMessage;
use App\Service\Analytics\AnalyticsRollupService;
use App\Service\Settings\SettingsServiceInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AggregateAnalyticsHandler
{
    public function __construct(private AnalyticsRollupService $rollup, private SettingsServiceInterface $settings)
    {
    }

    public function __invoke(AggregateAnalyticsMessage $message): void
    {
        $this->rollup->aggregateRecent(2);
        $this->rollup->pruneRawViews($this->settings->getInt('analytics.retention_days'));
    }
}
