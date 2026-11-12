<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Media;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/** @extends PersistentObjectFactory<Media> */
final class MediaFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Media::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'filename' => self::faker()->uuid().'.jpg',
            'originalName' => self::faker()->words(3, true).'.jpg',
            'mimeType' => 'image/jpeg',
            'size' => self::faker()->numberBetween(1024, 1024 * 1024),
            'width' => 1200,
            'height' => 800,
            'alt' => self::faker()->sentence(),
            'storageKey' => 'tests/'.self::faker()->unique()->uuid().'.jpg',
            'variants' => [],
        ];
    }
}
