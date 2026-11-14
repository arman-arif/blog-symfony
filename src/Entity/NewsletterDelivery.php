<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\IdentifiableTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Enum\NewsletterDeliveryStatus;
use App\Repository\NewsletterDeliveryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NewsletterDeliveryRepository::class)]
#[ORM\Table(name: 'newsletter_delivery')]
#[ORM\UniqueConstraint(name: 'uniq_newsletter_recipient', columns: ['campaign_id', 'recipient_id'])]
#[ORM\Index(name: 'idx_newsletter_pending', columns: ['campaign_id', 'status', 'recipient_id'])]
class NewsletterDelivery
{
    use IdentifiableTrait;
    use TimestampableTrait;

    #[ORM\ManyToOne(targetEntity: NewsletterCampaign::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private NewsletterCampaign $campaign;

    #[ORM\ManyToOne(targetEntity: NewsletterSubscriber::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?NewsletterSubscriber $subscriber = null;

    // Retain identity/accounting even after subscriber deletion; never copy email addresses.
    #[ORM\Column(type: Types::INTEGER)]
    private int $recipientId;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: NewsletterDeliveryStatus::class)]
    private NewsletterDeliveryStatus $status = NewsletterDeliveryStatus::Pending;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $attemptedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    public function __construct(NewsletterCampaign $campaign, NewsletterSubscriber $subscriber)
    {
        $this->campaign = $campaign;
        $this->subscriber = $subscriber;
        $this->recipientId = $subscriber->getId() ?? throw new \InvalidArgumentException('Persist the subscriber first.');
    }

    public function getCampaign(): NewsletterCampaign
    {
        return $this->campaign;
    }

    public function getSubscriber(): ?NewsletterSubscriber
    {
        return $this->subscriber;
    }

    public function getRecipientId(): int
    {
        return $this->recipientId;
    }

    public function getStatus(): NewsletterDeliveryStatus
    {
        return $this->status;
    }

    public function setStatus(NewsletterDeliveryStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getAttemptedAt(): ?\DateTimeImmutable
    {
        return $this->attemptedAt;
    }

    public function setAttemptedAt(?\DateTimeImmutable $attemptedAt): self
    {
        $this->attemptedAt = $attemptedAt;

        return $this;
    }

    public function getSentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function setSentAt(?\DateTimeImmutable $sentAt): self
    {
        $this->sentAt = $sentAt;

        return $this;
    }
}
