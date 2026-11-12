<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Entity\Media;
use App\Service\Media\MediaUrlResolver;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class MediaExtension extends AbstractExtension
{
    public function __construct(private readonly MediaUrlResolver $urls)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('media_url', $this->mediaUrl(...))];
    }

    public function mediaUrl(Media|string|null $media, string $variant = 'original'): string
    {
        return $this->urls->url($media, $variant);
    }
}
