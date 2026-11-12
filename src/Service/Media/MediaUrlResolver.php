<?php

declare(strict_types=1);

namespace App\Service\Media;

use App\Entity\Media;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class MediaUrlResolver
{
    public function __construct(
        private MediaStorageInterface $storage,
        #[Autowire(service: 'assets.packages')]
        private Packages $assets,
    ) {
    }

    public function url(Media|string|null $media, string $variant = 'original'): string
    {
        $key = $media instanceof Media ? $media->getStorageKey() : $media;
        if (null === $key || '' === $key) {
            return '';
        }

        if (str_starts_with($key, 'images/')) {
            return $this->assets->getUrl($key);
        }

        return $this->storage->url($key, $variant);
    }
}
