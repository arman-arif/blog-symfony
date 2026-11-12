<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final class SeoMetadata
{
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $focusKeyword = null;

    #[ORM\Column(type: Types::STRING, length: 2048, nullable: true)]
    private ?string $canonicalUrl = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $noindex = false;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $ogTitle = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $ogDescription = null;

    #[ORM\Column(type: Types::STRING, length: 2048, nullable: true)]
    private ?string $ogImageUrl = null;

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getFocusKeyword(): ?string
    {
        return $this->focusKeyword;
    }

    public function setFocusKeyword(?string $focusKeyword): self
    {
        $this->focusKeyword = $focusKeyword;

        return $this;
    }

    public function getCanonicalUrl(): ?string
    {
        return $this->canonicalUrl;
    }

    public function setCanonicalUrl(?string $canonicalUrl): self
    {
        $this->canonicalUrl = $canonicalUrl;

        return $this;
    }

    public function isNoindex(): bool
    {
        return $this->noindex;
    }

    public function setNoindex(bool $noindex): self
    {
        $this->noindex = $noindex;

        return $this;
    }

    public function getOgTitle(): ?string
    {
        return $this->ogTitle;
    }

    public function setOgTitle(?string $ogTitle): self
    {
        $this->ogTitle = $ogTitle;

        return $this;
    }

    public function getOgDescription(): ?string
    {
        return $this->ogDescription;
    }

    public function setOgDescription(?string $ogDescription): self
    {
        $this->ogDescription = $ogDescription;

        return $this;
    }

    public function getOgImageUrl(): ?string
    {
        return $this->ogImageUrl;
    }

    public function setOgImageUrl(?string $ogImageUrl): self
    {
        $this->ogImageUrl = $ogImageUrl;

        return $this;
    }
}
