<?php

declare(strict_types=1);

namespace App\Service\Media;

use App\Entity\Media;
use App\Repository\MediaReferenceRepository;

final readonly class MediaUsageChecker
{
    public function __construct(private MediaReferenceRepository $references)
    {
    }

    /** @return list<string> Human-readable places where this media record is referenced. */
    public function usages(Media $media): array
    {
        return $this->references->usages($media);
    }
}
