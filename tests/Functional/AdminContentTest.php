<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Mock\MockDataProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\TerminableInterface;

final class AdminContentTest extends AdminTestCase
{
    /** @return list<string> */
    private static function paths(): array
    {
        return [
            '/admin/media', '/admin/comments',
            '/admin/pages', '/admin/pages/new',
            '/admin/menus', '/admin/menus/new',
        ];
    }

    /** @return iterable<string, array{string}> */
    public static function routes(): iterable
    {
        foreach (self::paths() as $path) {
            foreach (['', '?state=loading', '?state=empty', '?state=error'] as $query) {
                yield $path.$query => [$path.$query];
            }
        }
    }

    #[DataProvider('routes')]
    public function testContentRoutesAndStates(string $path): void
    {
        $client = self::createClient();
        $client->request('GET', $path);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('main h1');
        self::assertSelectorExists('#admin-mobile-navigation');
        self::assertSelectorNotExists('main form form');
        self::assertSelectorNotExists('main script[src^="https:"]');
    }

    public function testAllContentLinksAndReadOnlyRoutes(): void
    {
        $client = self::createClient(['debug' => false]);
        $links = [];
        foreach (self::paths() as $path) {
            $crawler = $client->request('GET', $path);
            self::assertResponseIsSuccessful();
            $links = array_merge($links, $crawler->filter('a[href^="/"]')->each(static fn (Crawler $node): string => explode('#', $node->attr('href') ?? '')[0]));
            if ('/admin/media' === $path || str_starts_with($path, '/admin/menus') || str_starts_with($path, '/admin/pages')) {
                continue;
            }
            $client->request('POST', $path);
            self::assertResponseStatusCodeSame(405, $path);
        }
        foreach (array_unique($links) as $path) {
            if (preg_match('#^/admin/(articles|categories|tags)/\d+#', $path)) {
                continue;
            }
            $client->request('GET', $path);
            self::assertResponseIsSuccessful('Broken content link: '.$path);
        }
        foreach (['pages', 'menus'] as $module) {
            $client->request('GET', '/admin/'.$module.'/999/edit');
            self::assertResponseStatusCodeSame(404);
        }
    }

    public function testContentRoutesRequireAuthenticationAndProductionRedirects(): void
    {
        self::ensureKernelShutdown();
        $kernel = self::bootKernel();
        self::assertInstanceOf(TerminableInterface::class, $kernel);
        $request = Request::create('/admin/media');
        $response = $kernel->handle($request);
        self::assertSame(302, $response->getStatusCode());
        self::assertStringContainsString('/login', (string) $response->headers->get('Location'));
        $kernel->terminate($request, $response);
        self::ensureKernelShutdown();
        $kernel = self::bootKernel(['environment' => 'prod', 'debug' => false]);
        self::assertInstanceOf(TerminableInterface::class, $kernel);
        foreach (self::paths() as $path) {
            $request = Request::create($path);
            $response = $kernel->handle($request);
            self::assertSame(302, $response->getStatusCode(), $path);
            self::assertStringContainsString('/login', (string) $response->headers->get('Location'));
            $kernel->terminate($request, $response);
        }
    }

    public function testContentFixtureRelationshipsAndDates(): void
    {
        $data = new MockDataProvider();
        self::assertCount(7, $data->contentRecords('media'));
        self::assertCount(5, $data->contentRecords('comment'));
        self::assertCount(2, $data->contentRecords('menu'));
        self::assertCount(3, $data->contentRecords('ad'));
        foreach ($data->contentRecords('ad') as $ad) {
            self::assertInstanceOf(\DateTimeImmutable::class, $ad['startsAt']);
            self::assertInstanceOf(\DateTimeImmutable::class, $ad['endsAt']);
        }
        self::assertNull($data->contentRecord('category', 999));
        self::assertNull($data->contentRecord('category', null)['id']);
        self::assertSame('', $data->contentRecord('page', null)['content']);
    }
}
