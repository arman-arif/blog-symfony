<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class StyleguideTest extends WebTestCase
{
    public function testStyleguideIsNotAccessibleInProduction(): void
    {
        $kernel = self::bootKernel(['environment' => 'prod', 'debug' => false]);
        $response = $kernel->handle(Request::create('/_styleguide'));
        self::assertSame(404, $response->getStatusCode());
    }

    public function testComponentsRenderWithAccessibleStates(): void
    {
        $client = self::createClient();
        $client->request('GET', '/_styleguide');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[aria-invalid="true"]');
        self::assertSelectorExists('button[aria-busy="true"][disabled]');
        self::assertSelectorExists('dialog[aria-labelledby]');
        self::assertSelectorExists('[role="tab"][aria-selected="true"]');
        self::assertSelectorExists('table caption');
        self::assertSelectorNotExists('script[src^="https://"]');
    }
}
