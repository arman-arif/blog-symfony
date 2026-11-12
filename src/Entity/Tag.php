<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\IdentifiableTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Repository\TagRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: TagRepository::class)]
#[ORM\Table(name: 'tag')]
class Tag
{
    use IdentifiableTrait;
    use TimestampableTrait;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $name = '';

    #[Gedmo\Slug(fields: ['name'], updatable: false)]
    #[ORM\Column(type: Types::STRING, length: 120, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Embedded(class: SeoMetadata::class)]
    private SeoMetadata $seo;

    private int $articleCount = 0;

    public function __construct()
    {
        $this->seo = new SeoMetadata();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug ?? '';
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

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

    public function getSeo(): SeoMetadata
    {
        return $this->seo;
    }

    public function getArticleCount(): int
    {
        return $this->articleCount;
    }

    public function setArticleCount(int $articleCount): self
    {
        $this->articleCount = $articleCount;

        return $this;
    }

    public function setSeo(SeoMetadata $seo): self
    {
        $this->seo = $seo;

        return $this;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
