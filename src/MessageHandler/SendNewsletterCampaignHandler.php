<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\SendNewsletterCampaignMessage;
use App\Service\Newsletter\NewsletterCampaignService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SendNewsletterCampaignHandler
{
    public function __construct(private NewsletterCampaignService $campaigns)
    {
    }

    public function __invoke(SendNewsletterCampaignMessage $message): void
    {
        $this->campaigns->sendBatch($message->campaignId, $message->subscriberIds);
    }
}
