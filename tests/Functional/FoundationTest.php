<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class FoundationTest extends WebTestCase
{
    public function testHomepageBoots(): void
    {
        $client = self::createClient();
        $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Stay curious.');
    }
}
