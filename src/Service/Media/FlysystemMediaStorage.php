<?php

declare(strict_types=1);

namespace App\Service\Media;

use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class FlysystemMediaStorage implements MediaStorageInterface
{
    public function __construct(
        #[Autowire(service: 'media.storage')]
        private FilesystemOperator $filesystem,
        #[Autowire(param: 'app.media.public_base_url')]
        private string $publicBaseUrl,
    ) {
    }

    public function storeFile(string $storageKey, UploadedFile $file): void
    {
        $stream = fopen($file->getPathname(), 'rb');
        if (false === $stream) {
            throw new \RuntimeException('Unable to read the uploaded file.');
        }

        try {
            $this->filesystem->writeStream($storageKey, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    public function write(string $storageKey, string $contents): void
    {
        $this->filesystem->write($storageKey, $contents);
    }

    public function delete(string $storageKey): void
    {
        if ($this->filesystem->fileExists($storageKey)) {
            $this->filesystem->delete($storageKey);
        }
    }

    public function exists(string $storageKey): bool
    {
        return $this->filesystem->fileExists($storageKey);
    }

    public function url(string $storageKey, string $variant = 'original'): string
    {
        $base = rtrim($this->publicBaseUrl, '/');

        return 'original' === $variant
            ? $base.'/'.ltrim($storageKey, '/')
            : '/media/cache/resolve/'.$variant.'/'.ltrim($storageKey, '/');
    }
}
