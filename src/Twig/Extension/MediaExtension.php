<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Entity\Media;
use App\Service\Media\MediaStorageInterface;
use Symfony\Component\Asset\Packages;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class MediaExtension extends AbstractExtension
{
    public function __construct(
        private readonly MediaStorageInterface $storage,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire(service: 'assets.packages')]
        private readonly Packages $assets,
    ) {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('media_url', $this->mediaUrl(...))];
    }

    public function mediaUrl(Media|string|null $media, string $variant = 'original'): string
    {
        $key = $media instanceof Media ? $media->getStorageKey() : $media;
        if (null === $key || '' === $key) {
            return '';
        }

        // Seed fixtures reference bundled illustrations served by AssetMapper.
        if (str_starts_with($key, 'images/')) {
            return $this->assets->getUrl($key);
        }

        return $this->storage->url($key, $variant);
    }
}
