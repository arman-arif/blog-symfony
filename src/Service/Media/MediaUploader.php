<?php

declare(strict_types=1);

namespace App\Service\Media;

use App\Entity\Media;
use App\Entity\User;
use App\Service\Settings\SettingsServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
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

    public function __construct(
        private MediaStorageInterface $storage,
        private SettingsServiceInterface $settings,
        private EntityManagerInterface $entityManager,
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
        $dimensions = str_starts_with($mimeType, 'image/') ? $this->imageDimensions($file->getPathname()) : [null, null];

        if (str_starts_with($mimeType, 'image/')) {
            $this->storage->write($storageKey, $this->stripImageMetadata($file->getPathname(), $mimeType));
        } else {
            $this->storage->storeFile($storageKey, $file);
        }

        $media = (new Media())
            ->setFilename($filename)
            ->setOriginalName($originalName)
            ->setMimeType($mimeType)
            ->setSize((int) $file->getSize())
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

        $this->entityManager->persist($media);
        $this->entityManager->flush();

        return $media;
    }

    /** @return array{?int, ?int} */
    private function imageDimensions(string $path): array
    {
        $size = @getimagesize($path);
        if (false === $size) {
            throw new \DomainException('The uploaded image could not be read.');
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
        $ok = match ($mimeType) {
            'image/jpeg' => imagejpeg($image, null, 90),
            'image/png' => imagepng($image, null, 6),
            'image/webp' => imagewebp($image, null, 85),
            'image/gif' => imagegif($image),
        };
        $contents = (string) ob_get_clean();

        if (!$ok || '' === $contents) {
            throw new \DomainException('The uploaded image could not be re-encoded.');
        }

        return $contents;
    }
}
