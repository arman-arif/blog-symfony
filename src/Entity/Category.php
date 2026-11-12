<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\IdentifiableTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Repository\CategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: CategoryRepository::class)]
#[ORM\Table(name: 'category')]
#[Gedmo\Tree(type: 'nested')]
class Category
{
    use IdentifiableTrait;
    use TimestampableTrait;

    #[ORM\Column(type: Types::STRING, length: 150)]
    private string $name = '';

    #[Gedmo\Slug(fields: ['name'], updatable: false)]
    #[ORM\Column(type: Types::STRING, length: 180, unique: true)]
    private string $slug = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: Media::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Media $image = null;

    #[ORM\Embedded(class: SeoMetadata::class)]
    private SeoMetadata $seo;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Gedmo\TreeParent]
    private ?Category $parent = null;

    /** @var Collection<int, Category> */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    private Collection $children;

    #[ORM\Column(type: Types::INTEGER)]
    #[Gedmo\TreeLeft]
    private int $lft = 0;

    #[ORM\Column(type: Types::INTEGER)]
    #[Gedmo\TreeRight]
    private int $rgt = 0;

    #[ORM\Column(type: Types::INTEGER)]
    #[Gedmo\TreeLevel]
    private int $lvl = 0;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Gedmo\TreeRoot]
    private ?int $root = null;

    public function __construct()
    {
        $this->children = new ArrayCollection();
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
        return $this->slug;
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

    public function getImage(): ?Media
    {
        return $this->image;
    }

    public function setImage(?Media $image): self
    {
        $this->image = $image;

        return $this;
    }

    public function getSeo(): SeoMetadata
    {
        return $this->seo;
    }

    public function setSeo(SeoMetadata $seo): self
    {
        $this->seo = $seo;

        return $this;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): self
    {
        $this->parent = $parent;

        return $this;
    }

    /** @return Collection<int, Category> */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function getLft(): int
    {
        return $this->lft;
    }

    public function getRgt(): int
    {
        return $this->rgt;
    }

    public function getLvl(): int
    {
        return $this->lvl;
    }

    public function getRoot(): ?int
    {
        return $this->root;
    }

    public function setRoot(?int $root): self
    {
        $this->root = $root;

        return $this;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
