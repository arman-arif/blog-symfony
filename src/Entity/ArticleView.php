<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\IdentifiableTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Repository\ArticleViewRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ArticleViewRepository::class)]
#[ORM\Table(name: 'article_view')]
#[ORM\Index(name: 'idx_article_view_article_viewed_at', columns: ['article_id', 'viewed_at'])]
#[ORM\Index(name: 'idx_article_view_viewed_at', columns: ['viewed_at'])]
class ArticleView
{
    use IdentifiableTrait;
    use TimestampableTrait;

    #[ORM\ManyToOne(targetEntity: Article::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Article $article;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $viewedAt;

    #[ORM\Column(type: Types::STRING, length: 64)]
    private string $visitorHash;

    #[ORM\Column(type: Types::STRING, length: 2048, nullable: true)]
    private ?string $referrer = null;

    public function __construct()
    {
        $this->viewedAt = new \DateTimeImmutable();
    }

    public function getArticle(): Article
    {
        return $this->article;
    }

    public function setArticle(Article $article): self
    {
        $this->article = $article;

        return $this;
    }

    public function getViewedAt(): \DateTimeImmutable
    {
        return $this->viewedAt;
    }

    public function setViewedAt(\DateTimeImmutable $viewedAt): self
    {
        $this->viewedAt = $viewedAt;

        return $this;
    }

    public function getVisitorHash(): string
    {
        return $this->visitorHash;
    }

    public function setVisitorHash(string $visitorHash): self
    {
        $this->visitorHash = $visitorHash;

        return $this;
    }

    public function getReferrer(): ?string
    {
        return $this->referrer;
    }

    public function setReferrer(?string $referrer): self
    {
        $this->referrer = $referrer;

        return $this;
    }
}
