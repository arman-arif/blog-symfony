<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\SendScheduledNewsletterCampaignsMessage;
use App\Service\Newsletter\NewsletterCampaignService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SendScheduledNewsletterCampaignsHandler
{
    public function __construct(private NewsletterCampaignService $campaigns)
    {
    }

    public function __invoke(SendScheduledNewsletterCampaignsMessage $message): void
    {
        $this->campaigns->sendDueCampaigns();
    }
}
