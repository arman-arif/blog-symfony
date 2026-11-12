<?php

declare(strict_types=1);

namespace App\Service\Newsletter;

use App\Entity\NewsletterCampaign;
use App\Enum\NewsletterCampaignStatus;
use App\Enum\NewsletterSubscriberStatus;
use App\Message\SendNewsletterCampaignMessage;
use App\Repository\NewsletterCampaignRepository;
use App\Repository\NewsletterSubscriberRepository;
use App\Service\Article\ContentProcessor;
use App\Service\Mail\TransactionalMailer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class NewsletterCampaignService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private NewsletterCampaignRepository $campaigns,
        private NewsletterSubscriberRepository $subscribers,
        private NewsletterSubscriptionService $subscriptions,
        private TransactionalMailer $mailer,
        private MessageBusInterface $bus,
        private ContentProcessor $contentProcessor,
        private LoggerInterface $logger,
    ) {
    }

    public function save(NewsletterCampaign $campaign): NewsletterCampaign
    {
        $campaign->setHtml($this->contentProcessor->sanitize($campaign->getHtml()));
        $this->entityManager->persist($campaign);
        $this->entityManager->flush();

        return $campaign;
    }

    public function sendTest(NewsletterCampaign $campaign, string $email): void
    {
        $this->mailer->send($email, '[Test] '.$campaign->getSubject(), 'emails/newsletter.html.twig', [
            'campaign' => $campaign,
            'unsubscribeUrl' => '#',
        ]);
    }

    public function queue(NewsletterCampaign $campaign): int
    {
        $recipients = $this->subscribers->findBy(['status' => NewsletterSubscriberStatus::Confirmed]);
        $campaign->setStatus(NewsletterCampaignStatus::Sent)
            ->setSentAt(new \DateTimeImmutable())
            ->setStats(['sent' => 0, 'failed' => 0]);
        $this->entityManager->flush();

        foreach (array_chunk(array_map(static fn ($subscriber): int => (int) $subscriber->getId(), $recipients), 50) as $ids) {
            $this->bus->dispatch(new SendNewsletterCampaignMessage((int) $campaign->getId(), $ids));
        }

        return count($recipients);
    }

    /** @param list<int> $subscriberIds */
    public function sendBatch(int $campaignId, array $subscriberIds): void
    {
        $campaign = $this->campaigns->find($campaignId);
        if (null === $campaign) {
            return;
        }

        $sent = 0;
        $failed = 0;
        foreach ($this->subscribers->findBy(['id' => $subscriberIds]) as $subscriber) {
            try {
                $unsubscribe = $this->subscriptions->unsubscribeUrl($subscriber);
                $this->mailer->send($subscriber->getEmail(), $campaign->getSubject(), 'emails/newsletter.html.twig', [
                    'campaign' => $campaign,
                    'unsubscribeUrl' => $unsubscribe,
                ], ['List-Unsubscribe' => '<'.$unsubscribe.'>', 'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click']);
                ++$sent;
            } catch (\Throwable $exception) {
                ++$failed;
                $this->logger->error('Newsletter delivery failed.', ['subscriber' => $subscriber->getId(), 'campaign' => $campaignId, 'exception' => $exception]);
            }
        }

        $stats = $campaign->getStats();
        $campaign->setStats(['sent' => $stats['sent'] + $sent, 'failed' => $stats['failed'] + $failed]);
        $this->entityManager->flush();
    }

    public function sendDueCampaigns(?\DateTimeImmutable $now = null): int
    {
        $now ??= new \DateTimeImmutable();
        $due = $this->campaigns->createQueryBuilder('campaign')
            ->andWhere('campaign.status = :status')
            ->andWhere('campaign.scheduledAt IS NOT NULL')
            ->andWhere('campaign.scheduledAt <= :now')
            ->setParameter('status', NewsletterCampaignStatus::Scheduled)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();

        foreach ($due as $campaign) {
            $this->queue($campaign);
        }

        return count($due);
    }
}
