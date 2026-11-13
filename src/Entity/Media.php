<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\IdentifiableTrait;
use App\Entity\Traits\SoftDeleteableTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Repository\MediaRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: MediaRepository::class)]
#[ORM\Table(name: 'media')]
#[Gedmo\SoftDeleteable(fieldName: 'deletedAt', timeAware: false, hardDelete: false)]
class Media
{
    use IdentifiableTrait;
    use SoftDeleteableTrait;
    use TimestampableTrait;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $filename;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $originalName;

    #[ORM\Column(type: Types::STRING, length: 150)]
    private string $mimeType;

    #[ORM\Column(type: Types::INTEGER)]
    private int $size = 0;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $width = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $height = null;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    private ?string $alt = null;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    private ?string $caption = null;

    #[ORM\Column(type: Types::STRING, length: 500, unique: true)]
    private string $storageKey;

    /** @var array<string, string> */
    #[ORM\Column(type: Types::JSON)]
    private array $variants = [];

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $uploadedBy = null;

    /** Deleted rows are durable cleanup tombstones until originals and derivatives are removed. */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $filesDeletedAt = null;

    public function getFilesDeletedAt(): ?\DateTimeImmutable
    {
        return $this->filesDeletedAt;
    }

    public function setFilesDeletedAt(?\DateTimeImmutable $filesDeletedAt): self
    {
        $this->filesDeletedAt = $filesDeletedAt;

        return $this;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): self
    {
        $this->filename = $filename;

        return $this;
    }

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function setOriginalName(string $originalName): self
    {
        $this->originalName = $originalName;

        return $this;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): self
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function setSize(int $size): self
    {
        $this->size = $size;

        return $this;
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function setWidth(?int $width): self
    {
        $this->width = $width;

        return $this;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function setHeight(?int $height): self
    {
        $this->height = $height;

        return $this;
    }

    public function getAlt(): ?string
    {
        return $this->alt;
    }

    public function setAlt(?string $alt): self
    {
        $this->alt = $alt;

        return $this;
    }

    public function getCaption(): ?string
    {
        return $this->caption;
    }

    public function setCaption(?string $caption): self
    {
        $this->caption = $caption;

        return $this;
    }

    public function getStorageKey(): string
    {
        return $this->storageKey;
    }

    public function setStorageKey(string $storageKey): self
    {
        $this->storageKey = $storageKey;

        return $this;
    }

    /** @return array<string, string> */
    public function getVariants(): array
    {
        return $this->variants;
    }

    /** @param array<string, string> $variants */
    public function setVariants(array $variants): self
    {
        $this->variants = $variants;

        return $this;
    }

    public function getUploadedBy(): ?User
    {
        return $this->uploadedBy;
    }

    public function setUploadedBy(?User $uploadedBy): self
    {
        $this->uploadedBy = $uploadedBy;

        return $this;
    }
}
