<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\DataFixtures\DemoFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/** Public pages read real entities; each test loads the shared demo dataset in its transaction. */
abstract class FrontTestCase extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    protected function seed(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::getContainer()->get(DemoFixtures::class)->load($entityManager);
        self::getContainer()->get(TagAwareCacheInterface::class)->invalidateTags(['articles', 'homepage']);
        $contentCache = self::getContainer()->get('cache.content');
        if (method_exists($contentCache, 'clear')) {
            $contentCache->clear();
        }
    }
}
