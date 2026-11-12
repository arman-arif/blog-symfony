<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\EventSubscriber\ApiExceptionSubscriber;
use App\Service\Settings\DefaultSettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class FoundationInfrastructureTest extends KernelTestCase
{
    public function testSettingsServiceDefaultsAreTyped(): void
    {
        $settings = new DefaultSettingsService();

        self::assertSame('Fieldnotes', $settings->getString('general.name'));
        self::assertTrue($settings->getBool('comments.enabled'));
        self::assertFalse($settings->getBool('registration.enabled'));
        self::assertSame(90, $settings->getInt('analytics.retention_days'));
        self::assertSame([], $settings->getArray('appearance.social_links'));
    }

    public function testUnknownOrMistypedSettingsFailLoudly(): void
    {
        $settings = new DefaultSettingsService();

        $this->expectException(\OutOfBoundsException::class);
        $settings->getString('general.unknown');
    }

    public function testCachePoolWorksWithoutRedis(): void
    {
        self::bootKernel();

        $cache = self::getContainer()->get('cache.redis');
        self::assertInstanceOf(AdapterInterface::class, $cache);

        $item = $cache->getItem('foundation-check');
        $item->set('available');
        self::assertTrue($cache->save($item));

        $cache->deleteItem('foundation-check');
        self::assertFalse($cache->hasItem('foundation-check'));
    }

    public function testDatabaseConnectionIsConfigured(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertSame(1, (int) $entityManager->getConnection()->fetchOne('SELECT 1'));
    }

    public function testAsyncTransportIsInMemoryDuringTests(): void
    {
        self::bootKernel();

        self::assertInstanceOf(InMemoryTransport::class, self::getContainer()->get('messenger.transport.async'));
    }

    public function testApiErrorsAreProblemJson(): void
    {
        $event = new ExceptionEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create('/api/missing'),
            HttpKernelInterface::MAIN_REQUEST,
            new NotFoundHttpException('Not found'),
        );

        (new ApiExceptionSubscriber())($event);

        $response = $event->getResponse();
        self::assertInstanceOf(Response::class, $response);
        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        self::assertJsonStringEqualsJsonString(
            '{"type":"about:blank","title":"Not Found","status":404}',
            (string) $response->getContent(),
        );
    }

    public function testNonApiErrorsAreLeftToTheFramework(): void
    {
        $event = new ExceptionEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create('/front/path'),
            HttpKernelInterface::MAIN_REQUEST,
            new NotFoundHttpException('Not found'),
        );

        (new ApiExceptionSubscriber())($event);

        self::assertNull($event->getResponse());
    }
}
