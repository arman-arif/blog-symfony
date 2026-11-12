<?php

declare(strict_types=1);

namespace App\Service\Media;

use App\Entity\Media;
use Doctrine\ORM\EntityManagerInterface;

final readonly class MediaUsageChecker
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /** @return list<string> Human-readable places where this media record is referenced. */
    public function usages(Media $media): array
    {
        $checks = [
            'article featured image' => 'SELECT COUNT(article.id) FROM App\Entity\Article article WHERE article.featuredImage = :media',
            'category image' => 'SELECT COUNT(category.id) FROM App\Entity\Category category WHERE category.image = :media',
            'advertisement image' => 'SELECT COUNT(advertisement.id) FROM App\Entity\Advertisement advertisement WHERE advertisement.image = :media',
            'profile avatar' => 'SELECT COUNT(user.id) FROM App\Entity\User user WHERE user.avatar = :media',
        ];

        $usages = [];
        foreach ($checks as $label => $dql) {
            $count = (int) $this->entityManager->createQuery($dql)
                ->setParameter('media', $media)
                ->getSingleScalarResult();
            if ($count > 0) {
                $usages[] = $label;
            }
        }

        return $usages;
    }
}
