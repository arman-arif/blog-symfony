<?php

declare(strict_types=1);

namespace App\Service\Monetization;

use App\Entity\Advertisement;
use Doctrine\ORM\EntityManagerInterface;

final readonly class AdvertisementService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AdvertisementRenderer $renderer,
    ) {
    }

    public function save(Advertisement $advertisement): Advertisement
    {
        $this->entityManager->persist($advertisement);
        $this->entityManager->flush();
        $this->renderer->invalidate();

        return $advertisement;
    }

    public function delete(Advertisement $advertisement): void
    {
        $this->entityManager->remove($advertisement);
        $this->entityManager->flush();
        $this->renderer->invalidate();
    }

    public function toggle(Advertisement $advertisement): void
    {
        $advertisement->setIsActive(!$advertisement->isActive());
        $this->entityManager->flush();
        $this->renderer->invalidate();
    }
}
