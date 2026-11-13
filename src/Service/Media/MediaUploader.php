<?php

declare(strict_types=1);

namespace App\Service\Media;

use App\Entity\Media;
use App\Entity\User;
use App\Service\Settings\SettingsServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class MediaUploader
{
    /** @var array<string, list<string>> */
    private const array ALLOWED_TYPES = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/webp' => ['webp'],
        'image/gif' => ['gif'],
        'application/pdf' => ['pdf'],
        'text/plain' => ['txt'],
        'text/csv' => ['csv'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
    ];

    private const array BLOCKED_SEGMENTS = [
        'php', 'php3', 'php4', 'php5', 'phtml', 'phar', 'cgi', 'pl', 'py', 'sh', 'bash', 'exe', 'com', 'bat',
        'js', 'mjs', 'html', 'htm', 'svg', 'xml', 'htaccess',
    ];

    private const int MAX_DIMENSION = 8192;
    private const int MAX_PIXELS = 16_000_000;
    private const int MAX_DECODE_BYTES = 268_435_456;

    public function __construct(
        private MediaStorageInterface $storage,
        private SettingsServiceInterface $settings,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    public function upload(UploadedFile $file, ?User $uploadedBy): Media
    {
        if (!$file->isValid()) {
            throw new \DomainException('The upload did not complete. Please try again.');
        }

        $maxBytes = $this->settings->getInt('media.max_upload_bytes');
        if ((int) $file->getSize() > $maxBytes) {
            throw new \DomainException(sprintf('The file is larger than the %s MB upload limit.', number_format($maxBytes / 1_048_576, 1)));
        }

        $originalName = (string) $file->getClientOriginalName();
        $segments = array_map('strtolower', explode('.', $originalName));
        foreach (array_slice($segments, 0, -1) as $segment) {
            if (in_array($segment, self::BLOCKED_SEGMENTS, true)) {
                throw new \DomainException('This filename contains a blocked extension.');
            }
        }

        $mimeType = (new \finfo(\FILEINFO_MIME_TYPE))->file($file->getPathname()) ?: '';
        if (!isset(self::ALLOWED_TYPES[$mimeType])) {
            throw new \DomainException('Only JPEG, PNG, WebP, GIF, PDF, TXT, CSV and DOCX files are accepted.');
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, self::ALLOWED_TYPES[$mimeType], true)) {
            throw new \DomainException('The file extension does not match its detected type.');
        }

        $datePath = date('Y/m');
        $filename = bin2hex(random_bytes(16)).'.'.$extension;
        $storageKey = $datePath.'/'.$filename;
        $dimensions = str_starts_with($mimeType, 'image/') ? $this->imageDimensions($file->getPathname(), $mimeType, (int) $file->getSize()) : [null, null];
        $contents = str_starts_with($mimeType, 'image/') ? $this->stripImageMetadata($file->getPathname(), $mimeType) : null;

        $media = (new Media())
            ->setFilename($filename)
            ->setOriginalName($originalName)
            ->setMimeType($mimeType)
            ->setSize(null === $contents ? (int) $file->getSize() : strlen($contents))
            ->setWidth($dimensions[0])
            ->setHeight($dimensions[1])
            ->setAlt(pathinfo($originalName, \PATHINFO_FILENAME))
            ->setStorageKey($storageKey)
            ->setVariants([
                'original' => $this->storage->url($storageKey),
                'thumbnail' => $this->storage->url($storageKey, 'thumbnail'),
                'medium' => $this->storage->url($storageKey, 'medium'),
                'large' => $this->storage->url($storageKey, 'large'),
                'webp' => $this->storage->url($storageKey, 'webp'),
            ])
            ->setUploadedBy($uploadedBy);

        try {
            if (null !== $contents) {
                $this->storage->write($storageKey, $contents);
            } else {
                $this->storage->storeFile($storageKey, $file);
            }
            $this->entityManager->wrapInTransaction(function () use ($media): void {
                $this->entityManager->persist($media);
            });
        } catch (\Throwable $exception) {
            try {
                $this->storage->delete($storageKey);
            } catch (\Exception $cleanupException) {
                $this->logger->error('Failed upload left an orphaned storage key.', ['storageKey' => $storageKey, 'exception' => $cleanupException]);
            }
            throw $exception;
        }

        return $media;
    }

    /** @return array{?int, ?int} */
    private function imageDimensions(string $path, string $mimeType, int $encodedBytes): array
    {
        $size = @getimagesize($path);
        if (false === $size || $size['mime'] !== $mimeType || $size[0] < 1 || $size[1] < 1) {
            throw new \DomainException('The uploaded image could not be read.');
        }

        // Read headers and bound allocation before any GD raster decoder runs.
        if ($size[0] > self::MAX_DIMENSION || $size[1] > self::MAX_DIMENSION) {
            throw new \DomainException('The image exceeds the 8192-pixel dimension limit.');
        }
        $pixels = $size[0] * $size[1];
        if ($pixels > self::MAX_PIXELS) {
            throw new \DomainException('The image exceeds the 16-megapixel limit.');
        }
        // Conservative headroom for raster, encoder buffers and compressed copies.
        $requiredBytes = $pixels * 16 + $encodedBytes * 3 + 16_777_216;
        $memoryLimit = ini_parse_quantity((string) ini_get('memory_limit'));
        $available = $memoryLimit < 0 ? self::MAX_DECODE_BYTES : min(self::MAX_DECODE_BYTES, $memoryLimit - memory_get_usage(true));
        if ($requiredBytes > $available) {
            throw new \DomainException('The image exceeds the safe decoding memory limit.');
        }

        return [$size[0], $size[1]];
    }

    private function stripImageMetadata(string $path, string $mimeType): string
    {
        $image = match ($mimeType) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            'image/gif' => @imagecreatefromgif($path),
            default => false,
        };

        if (false === $image) {
            throw new \DomainException('The uploaded image could not be processed.');
        }

        if ('image/png' === $mimeType) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
        }

        ob_start();
        try {
            $ok = match ($mimeType) {
                'image/jpeg' => imagejpeg($image, null, 90),
                'image/png' => imagepng($image, null, 6),
                'image/webp' => imagewebp($image, null, 85),
                'image/gif' => imagegif($image),
            };
            $contents = (string) ob_get_contents();
        } finally {
            ob_end_clean();
            unset($image);
        }

        if (!$ok || '' === $contents) {
            throw new \DomainException('The uploaded image could not be re-encoded.');
        }

        return $contents;
    }
}
