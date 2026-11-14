<?php

declare(strict_types=1);

namespace App\Service\Newsletter;

use App\Entity\NewsletterCampaign;
use App\Entity\NewsletterDelivery;
use App\Entity\NewsletterSubscriber;
use App\Enum\NewsletterCampaignStatus;
use App\Enum\NewsletterDeliveryStatus;
use App\Enum\NewsletterSubscriberStatus;
use App\Message\SendNewsletterCampaignMessage;
use App\Repository\NewsletterCampaignRepository;
use App\Repository\NewsletterDeliveryRepository;
use App\Repository\NewsletterSubscriberRepository;
use App\Service\Article\ContentProcessor;
use App\Service\Mail\TransactionalMailer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class NewsletterCampaignService
{
    public const int BATCH_SIZE = 50;
    private const string STALE_ATTEMPT = '-15 minutes';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private NewsletterCampaignRepository $campaigns,
        private NewsletterSubscriberRepository $subscribers,
        private NewsletterDeliveryRepository $deliveries,
        private NewsletterSubscriptionService $subscriptions,
        private TransactionalMailer $mailer,
        private MessageBusInterface $bus,
        private ContentProcessor $contentProcessor,
        private LoggerInterface $logger,
    ) {
    }

    public function save(NewsletterCampaign $campaign, ?NewsletterCampaign $details = null): NewsletterCampaign
    {
        $details ??= clone $campaign;
        $this->transaction(function () use ($campaign, $details): void {
            $current = null === $campaign->getId() ? $campaign : $this->campaigns->findForUpdate($campaign->getId());
            if (null === $current || !$current->isEditable() || !$details->isEditable()) {
                throw new \DomainException('Queued or completed campaigns cannot be edited.');
            }
            $current->setSubject($details->getSubject())->setHtml($this->contentProcessor->sanitize($details->getHtml()))
                ->setStatus($details->getStatus())->setScheduledAt($details->getScheduledAt());
            $this->entityManager->persist($current);
        });

        return $campaign;
    }

    public function sendTest(NewsletterCampaign $campaign, string $email): void
    {
        $this->mailer->send($email, '[Test] '.$campaign->getSubject(), 'emails/newsletter.html.twig', [
            'campaign' => $campaign,
            'unsubscribeUrl' => '#',
        ]);
    }

    public function queue(NewsletterCampaign $campaign, ?\DateTimeImmutable $dueAt = null): int
    {
        $id = $campaign->getId() ?? throw new \DomainException('Save the campaign before queueing it.');
        $count = $this->transaction(function () use ($id, $dueAt): int {
            $current = $this->campaigns->findForUpdate($id) ?? throw new \DomainException('Campaign no longer exists.');
            if (!$current->isEditable()) {
                return $current->getStats()['total'];
            }
            if (null !== $dueAt && (NewsletterCampaignStatus::Scheduled !== $current->getStatus()
                || null === $current->getScheduledAt() || $current->getScheduledAt() > $dueAt)) {
                // An administrator may have postponed/unscheduled it after the scheduler query.
                return 0;
            }
            $current->setStatus(NewsletterCampaignStatus::Queued)->setSentAt(null);
            $after = 0;
            $total = 0;
            $upper = $this->subscribers->highestId();
            // Persist a durable audience/outbox in bounded chunks, without hydrating the list.
            while ([] !== $ids = $this->subscribers->confirmedIdsAfter($after, $upper, self::BATCH_SIZE)) {
                $total += count($ids);
                $batch = [];
                foreach ($ids as $recipientId) {
                    $subscriber = $this->entityManager->getReference(NewsletterSubscriber::class, $recipientId);
                    \assert(null !== $subscriber);
                    $delivery = new NewsletterDelivery($current, $subscriber);
                    $this->entityManager->persist($delivery);
                    $batch[] = $delivery;
                    $after = $recipientId;
                }
                $this->entityManager->flush();
                foreach ($batch as $delivery) {
                    $this->entityManager->detach($delivery);
                    $subscriber = $delivery->getSubscriber();
                    if (null !== $subscriber && $this->entityManager->isUninitializedObject($subscriber)) {
                        $this->entityManager->detach($subscriber);
                    }
                }
            }
            $current->setStats(['sent' => 0, 'failed' => 0, 'skipped' => 0, 'uncertain' => 0, 'pending' => $total, 'sending' => 0, 'total' => $total]);
            $this->finishIfComplete($current);

            return $current->getStats()['total'];
        });
        // Lost dispatches are recovered from pending ledger rows by the scheduler.
        $this->dispatchPending($id);

        return $count;
    }

    /** @param list<int> $subscriberIds */
    public function sendBatch(int $campaignId, array $subscriberIds): void
    {
        if (count($subscriberIds) > self::BATCH_SIZE) {
            throw new \InvalidArgumentException('Newsletter batches must contain at most 50 recipients.');
        }
        foreach (array_unique($subscriberIds) as $recipientId) {
            if ($this->reserve($campaignId, $recipientId)) {
                $this->deliver($campaignId, $recipientId);
            }
        }
    }

    public function sendDueCampaigns(?\DateTimeImmutable $now = null): int
    {
        $now ??= new \DateTimeImmutable();
        $after = 0;
        $count = 0;
        while ([] !== $ids = $this->campaigns->dueOrUnfinishedIds($now, $after)) {
            foreach ($ids as $id) {
                $campaign = $this->campaigns->find($id);
                if (null !== $campaign) {
                    $this->queue($campaign, $now);
                    $this->entityManager->detach($campaign);
                    ++$count;
                }
                $after = $id;
            }
        }

        return $count;
    }

    private function dispatchPending(int $campaignId): void
    {
        $after = 0;
        $staleBefore = new \DateTimeImmutable(self::STALE_ATTEMPT);
        while ([] !== $ids = $this->deliveries->pendingRecipientIds($campaignId, $after, $staleBefore, self::BATCH_SIZE)) {
            $this->bus->dispatch(new SendNewsletterCampaignMessage($campaignId, $ids));
            $after = $ids[array_key_last($ids)];
        }
    }

    private function reserve(int $campaignId, int $recipientId): bool
    {
        return $this->transaction(function () use ($campaignId, $recipientId): bool {
            $campaign = $this->campaigns->findForUpdate($campaignId);
            if (null === $campaign || !in_array($campaign->getStatus(), [NewsletterCampaignStatus::Queued, NewsletterCampaignStatus::Sending], true)) {
                return false;
            }
            $delivery = $this->deliveries->findForUpdate($campaignId, $recipientId);
            if (null === $delivery) {
                return false;
            }
            if (NewsletterDeliveryStatus::Sending === $delivery->getStatus()
                && (null === $delivery->getAttemptedAt() || $delivery->getAttemptedAt() <= new \DateTimeImmutable(self::STALE_ATTEMPT))) {
                // SMTP cannot participate in a DB transaction. Never blindly replay an unknown outcome.
                $this->recordStatus($campaign, $delivery, NewsletterDeliveryStatus::Uncertain);
                $this->entityManager->flush();
                $this->detachDelivery($delivery);

                return false;
            }
            if (NewsletterDeliveryStatus::Pending !== $delivery->getStatus()) {
                $this->detachDelivery($delivery);

                return false;
            }
            $delivery->setAttemptedAt(new \DateTimeImmutable());
            $this->recordStatus($campaign, $delivery, NewsletterDeliveryStatus::Sending);

            return true;
        });
    }

    private function deliver(int $campaignId, int $recipientId): void
    {
        $this->transaction(function () use ($campaignId, $recipientId): void {
            // Serialize accounting/replays across hosts. Keep the subscriber row lock through SMTP
            // so an unsubscribe completed before this attempt is always respected.
            $campaign = $this->campaigns->findForUpdate($campaignId);
            $delivery = $this->deliveries->findForUpdate($campaignId, $recipientId);
            if (null === $campaign || null === $delivery || NewsletterDeliveryStatus::Sending !== $delivery->getStatus()) {
                return;
            }
            $subscriber = $this->subscribers->findForUpdate($recipientId);
            if (null === $subscriber || NewsletterSubscriberStatus::Confirmed !== $subscriber->getStatus() || null !== $subscriber->getUnsubscribedAt()) {
                $this->recordStatus($campaign, $delivery, NewsletterDeliveryStatus::Skipped);
            } else {
                try {
                    $unsubscribe = $this->subscriptions->unsubscribeUrl($subscriber);
                    $this->mailer->deliver($subscriber->getEmail(), $campaign->getSubject(), 'emails/newsletter.html.twig', [
                        'campaign' => $campaign, 'unsubscribeUrl' => $unsubscribe,
                    ], ['List-Unsubscribe' => '<'.$unsubscribe.'>', 'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click']);
                    $status = NewsletterDeliveryStatus::Sent;
                } catch (\Throwable $exception) {
                    $status = NewsletterDeliveryStatus::Failed;
                    $this->logger->error('Newsletter delivery failed; automatic resend suppressed.', [
                        'subscriber' => $recipientId, 'campaign' => $campaignId, 'exception' => $exception,
                    ]);
                }
                if (NewsletterDeliveryStatus::Sent === $status) {
                    $delivery->setSentAt(new \DateTimeImmutable());
                }
                // Database/accounting errors must propagate, leaving the durable attempt uncertain.
                $this->recordStatus($campaign, $delivery, $status);
            }
            $this->entityManager->flush();
            $this->detachDelivery($delivery);
            if (null !== $subscriber) {
                $this->entityManager->detach($subscriber);
            }
        });
    }

    private function detachDelivery(NewsletterDelivery $delivery): void
    {
        $subscriber = $delivery->getSubscriber();
        $this->entityManager->detach($delivery);
        if (null !== $subscriber && $this->entityManager->isUninitializedObject($subscriber)) {
            $this->entityManager->detach($subscriber);
        }
    }

    private function recordStatus(NewsletterCampaign $campaign, NewsletterDelivery $delivery, NewsletterDeliveryStatus $status): void
    {
        // The campaign was freshly loaded with FOR UPDATE. Ledger and counters commit together;
        // no full-audience aggregation per recipient and no lost read/modify/write updates.
        $stats = $campaign->getStats();
        --$stats[$delivery->getStatus()->value];
        ++$stats[$status->value];
        $delivery->setStatus($status);
        $campaign->setStats($stats);
        $this->finishIfComplete($campaign);
    }

    private function finishIfComplete(NewsletterCampaign $campaign): void
    {
        $stats = $campaign->getStats();
        if (0 === $stats['pending'] + $stats['sending']) {
            $campaign->setStatus($stats['failed'] + $stats['uncertain'] > 0 ? NewsletterCampaignStatus::Failed : NewsletterCampaignStatus::Sent)
                ->setSentAt(new \DateTimeImmutable());
        } elseif ($stats['sending'] + $stats['sent'] + $stats['failed'] + $stats['skipped'] + $stats['uncertain'] > 0) {
            $campaign->setStatus(NewsletterCampaignStatus::Sending);
        }
    }

    /** @template T
     * @param callable(): T $operation
     *
     * @return T
     */
    private function transaction(callable $operation): mixed
    {
        return $this->entityManager->wrapInTransaction($operation);
    }
}
