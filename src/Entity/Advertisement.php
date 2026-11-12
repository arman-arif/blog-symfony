<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\IdentifiableTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Enum\AdvertisementPlacement;
use App\Enum\AdvertisementType;
use App\Repository\AdvertisementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AdvertisementRepository::class)]
#[ORM\Table(name: 'advertisement')]
#[ORM\Index(name: 'idx_advertisement_active', columns: ['is_active', 'placement'])]
class Advertisement
{
    use IdentifiableTrait;
    use TimestampableTrait;

    #[ORM\Column(type: Types::STRING, length: 150)]
    private string $name;

    #[ORM\Column(type: Types::STRING, length: 30, enumType: AdvertisementPlacement::class)]
    private AdvertisementPlacement $placement = AdvertisementPlacement::Sidebar;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: AdvertisementType::class)]
    private AdvertisementType $type = AdvertisementType::Image;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $content = null;

    #[ORM\ManyToOne(targetEntity: Media::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Media $image = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $startsAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $endsAt = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isActive = false;

    #[ORM\Column(type: Types::INTEGER)]
    private int $priority = 10;

    #[ORM\Column(name: 'target_url', type: Types::STRING, length: 2048, nullable: true)]
    private ?string $targetUrl = null;

    #[ORM\Column(name: 'after_paragraph', type: Types::INTEGER, nullable: true)]
    private ?int $afterParagraph = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getPlacement(): AdvertisementPlacement
    {
        return $this->placement;
    }

    public function setPlacement(AdvertisementPlacement $placement): self
    {
        $this->placement = $placement;

        return $this;
    }

    public function getType(): AdvertisementType
    {
        return $this->type;
    }

    public function setType(AdvertisementType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getImage(): ?Media
    {
        return $this->image;
    }

    public function setImage(?Media $image): self
    {
        $this->image = $image;

        return $this;
    }

    public function getStartsAt(): ?\DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function setStartsAt(?\DateTimeImmutable $startsAt): self
    {
        $this->startsAt = $startsAt;

        return $this;
    }

    public function getEndsAt(): ?\DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function setEndsAt(?\DateTimeImmutable $endsAt): self
    {
        $this->endsAt = $endsAt;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getTargetUrl(): ?string
    {
        return $this->targetUrl;
    }

    public function setTargetUrl(?string $targetUrl): self
    {
        $this->targetUrl = $targetUrl;

        return $this;
    }

    public function getAfterParagraph(): ?int
    {
        return $this->afterParagraph;
    }

    public function setAfterParagraph(?int $afterParagraph): self
    {
        $this->afterParagraph = $afterParagraph;

        return $this;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
