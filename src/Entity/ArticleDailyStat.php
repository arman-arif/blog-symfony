<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\IdentifiableTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Repository\ArticleDailyStatRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ArticleDailyStatRepository::class)]
#[ORM\Table(name: 'article_daily_stat')]
#[ORM\UniqueConstraint(name: 'uniq_article_daily_stat_day', columns: ['article_id', 'stat_day'])]
class ArticleDailyStat
{
    use IdentifiableTrait;
    use TimestampableTrait;

    #[ORM\ManyToOne(targetEntity: Article::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Article $article;

    #[ORM\Column(name: 'stat_day', type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $statDate;

    #[ORM\Column(type: Types::INTEGER)]
    private int $views = 0;

    public function getArticle(): Article
    {
        return $this->article;
    }

    public function setArticle(Article $article): self
    {
        $this->article = $article;

        return $this;
    }

    public function getStatDate(): \DateTimeImmutable
    {
        return $this->statDate;
    }

    public function setStatDate(\DateTimeImmutable $statDate): self
    {
        $this->statDate = $statDate->setTime(0, 0);

        return $this;
    }

    public function getViews(): int
    {
        return $this->views;
    }

    public function setViews(int $views): self
    {
        $this->views = $views;

        return $this;
    }

    public function incrementViews(int $amount = 1): self
    {
        $this->views += $amount;

        return $this;
    }
}
