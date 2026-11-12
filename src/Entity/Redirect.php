<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\IdentifiableTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Repository\RedirectRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RedirectRepository::class)]
#[ORM\Table(name: 'redirect')]
class Redirect
{
    use IdentifiableTrait;
    use TimestampableTrait;

    #[ORM\Column(type: Types::STRING, length: 512, unique: true)]
    private string $sourcePath;

    #[ORM\Column(type: Types::STRING, length: 2048)]
    private string $targetUrl;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $statusCode = 301;

    #[ORM\Column(type: Types::INTEGER)]
    private int $hits = 0;

    public function getSourcePath(): string
    {
        return $this->sourcePath;
    }

    public function setSourcePath(string $sourcePath): self
    {
        $this->sourcePath = $sourcePath;

        return $this;
    }

    public function getTargetUrl(): string
    {
        return $this->targetUrl;
    }

    public function setTargetUrl(string $targetUrl): self
    {
        $this->targetUrl = $targetUrl;

        return $this;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function setStatusCode(int $statusCode): self
    {
        $this->statusCode = $statusCode;

        return $this;
    }

    public function getHits(): int
    {
        return $this->hits;
    }

    public function setHits(int $hits): self
    {
        $this->hits = $hits;

        return $this;
    }

    public function incrementHits(): self
    {
        ++$this->hits;

        return $this;
    }
}
