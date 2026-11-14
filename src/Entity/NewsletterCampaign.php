<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\IdentifiableTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Enum\NewsletterCampaignStatus;
use App\Repository\NewsletterCampaignRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NewsletterCampaignRepository::class)]
#[ORM\Table(name: 'newsletter_campaign')]
class NewsletterCampaign
{
    use IdentifiableTrait;
    use TimestampableTrait;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $subject;

    #[ORM\Column(type: Types::TEXT)]
    private string $html = '';

    #[ORM\Column(type: Types::STRING, length: 20, enumType: NewsletterCampaignStatus::class)]
    private NewsletterCampaignStatus $status = NewsletterCampaignStatus::Draft;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $scheduledAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    /** @var array{sent: int, failed: int, skipped?: int, uncertain?: int, pending?: int, sending?: int, total?: int} */
    #[ORM\Column(type: Types::JSON)]
    private array $stats = ['sent' => 0, 'failed' => 0];

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    public function getHtml(): string
    {
        return $this->html;
    }

    public function setHtml(string $html): self
    {
        $this->html = $html;

        return $this;
    }

    public function getStatus(): NewsletterCampaignStatus
    {
        return $this->status;
    }

    public function setStatus(NewsletterCampaignStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getScheduledAt(): ?\DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function setScheduledAt(?\DateTimeImmutable $scheduledAt): self
    {
        $this->scheduledAt = $scheduledAt;

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

    public function isEditable(): bool
    {
        return in_array($this->status, [NewsletterCampaignStatus::Draft, NewsletterCampaignStatus::Scheduled], true);
    }

    /** @return array{sent: int, failed: int, skipped: int, uncertain: int, pending: int, sending: int, total: int} */
    public function getStats(): array
    {
        return $this->stats + ['skipped' => 0, 'uncertain' => 0, 'pending' => 0, 'sending' => 0, 'total' => $this->stats['sent'] + $this->stats['failed']];
    }

    /** @param array{sent: int, failed: int, skipped?: int, uncertain?: int, pending?: int, sending?: int, total?: int} $stats */
    public function setStats(array $stats): self
    {
        $this->stats = $stats;

        return $this;
    }
}
