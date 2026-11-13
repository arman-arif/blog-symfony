<?php

declare(strict_types=1);

namespace App\Service\Media;

use App\Entity\Media;
use App\Repository\MediaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final readonly class MediaDeletionService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MediaRepository $media,
        private MediaUsageChecker $usageChecker,
        private MediaStorageInterface $storage,
        private LoggerInterface $logger,
    ) {
    }

    /** False means the deletion is saved but physical cleanup needs retrying. */
    public function delete(Media $media): bool
    {
        $id = $media->getId() ?? throw new \DomainException('This media has not been saved.');
        $key = $media->getStorageKey();
        $usages = $this->usageChecker->usages($media);
        if ([] !== $usages) {
            throw new \DomainException('This file is used by '.implode(', ', $usages).'. Remove those references before deleting it.');
        }
        $this->entityManager->wrapInTransaction(function () use ($media): void {
            $usages = $this->usageChecker->usages($media);
            if ([] !== $usages) {
                throw new \DomainException('This file is used by '.implode(', ', $usages).'. Remove those references before deleting it.');
            }
            $this->entityManager->remove($media);
        });

        // Never remove bytes until the database deletion/tombstone succeeds.
        return $this->cleanup($id, $key);
    }

    /** @return array{cleaned: int, failed: int} */
    public function retryPending(): array
    {
        $after = 0;
        $counts = ['cleaned' => 0, 'failed' => 0];
        while ([] !== ($rows = $this->media->pendingFileCleanup($after))) {
            foreach ($rows as $row) {
                ++$counts[$this->cleanup($row['id'], $row['storageKey']) ? 'cleaned' : 'failed'];
                $after = $row['id'];
            }
        }

        return $counts;
    }

    private function cleanup(int $id, string $key): bool
    {
        try {
            $this->storage->delete($key);
            $this->media->markFilesDeleted($id);

            return true;
        } catch (\Exception $exception) {
            $this->logger->error('Deleted media needs physical cleanup retry.', ['mediaId' => $id, 'exception' => $exception]);

            return false;
        }
    }
}
