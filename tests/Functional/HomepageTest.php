<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HomepageTest extends WebTestCase
{
    public function testHomepageLinksAndLocalImagesResolve(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('nav[aria-label="Main navigation"]');
        self::assertSelectorExists('#newsletter input[type="email"]');
        $links = $crawler->filter('a[href^="/"]')->each(static fn ($node): string => $node->attr('href') ?? '');
        foreach (array_unique($links) as $link) {
            $client->request('GET', $link);
            self::assertResponseIsSuccessful('Broken homepage link: '.$link);
        }
        $crawler->filter('img')->each(static function ($image): void {
            self::assertSame('lazy', $image->attr('loading'));
            self::assertNotNull($image->attr('width'));
            self::assertNotNull($image->attr('height'));
            self::assertNotEmpty($image->attr('alt'));
        });
    }
}
