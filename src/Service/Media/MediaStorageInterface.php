<?php

declare(strict_types=1);

namespace App\Service\Media;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/** Storage abstraction: local Flysystem today, S3-compatible storage later. */
interface MediaStorageInterface
{
    public function storeFile(string $storageKey, UploadedFile $file): void;

    public function write(string $storageKey, string $contents): void;

    public function delete(string $storageKey): void;

    public function exists(string $storageKey): bool;

    public function url(string $storageKey, string $variant = 'original'): string;
}
