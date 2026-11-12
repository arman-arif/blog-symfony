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
            '/admin/pages', '/admin/pages/new', '/admin/pages/1/edit',
            '/admin/menus', '/admin/menus/new', '/admin/menus/1/edit',
            '/admin/advertisements', '/admin/advertisements/new', '/admin/advertisements/1/edit',
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

    public function testModerationStatesAndLocalReply(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/comments');
        self::assertSelectorCount(5, '[data-table-tab]');
        self::assertSelectorCount(5, '[data-row]');
        foreach (['pending', 'approved', 'rejected', 'spam'] as $status) {
            self::assertSelectorExists('[data-row][data-status="'.$status.'"]');
        }
        self::assertSelectorExists('[data-moderate="approved"][disabled]');
        self::assertSelectorExists('#moderation-reply-form fieldset[disabled]');
        self::assertSelectorExists('#moderation-reply[minlength="10"]');
        self::assertSelectorTextContains('[data-id="2"]', 'Reply to comment #1');
        self::assertSelectorTextContains('[data-id="4"]', 'reader report');
    }

    public function testPagesReuseEditorAndEscapedContent(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/pages/1/edit');
        self::assertSelectorExists('[data-component="editor"]');
        self::assertSelectorTextContains('[data-editor-surface]', 'Why Fieldnotes exists');
        self::assertSelectorExists('textarea[name="content"][readonly]');
        self::assertSelectorExists('[data-content-preview]');
        self::assertSelectorExists('#content-status option[value="published"][selected]');
        self::assertSelectorExists('#content-preview-dialog');
        self::assertSelectorCount(6, '[data-media-select]');
    }

    public function testMenuStructureAndAdvertisingCodeStayInert(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/menus/1/edit');
        self::assertSelectorCount(4, '[data-menu-root] [data-menu-item]');
        self::assertSelectorCount(2, '[data-id="1"] > ol > li');
        self::assertSelectorExists('[data-menu-root] [data-menu-move="indent"]');
        self::assertSelectorExists('[data-menu-root] [data-menu-move="outdent"]');
        self::assertSelectorExists('[data-menu-output][readonly]');
        self::assertSelectorExists('#menu-location option[value="header"][selected]');
        $client->request('GET', '/admin/advertisements/2/edit');
        self::assertSelectorTextContains('#ad-content', '<p>Support independent writing.</p>');
        self::assertSelectorNotExists('main p:contains("Support independent writing.")');
        self::assertSelectorExists('#ad-type option[value="html"][selected]');
        self::assertSelectorExists('#ad-start[value="2026-12-01T00:00"]');
        self::assertSelectorExists('#ad-end[value="2026-12-31T00:00"]');
        self::assertSelectorExists('#ad-priority[min="0"][max="100"]');
        self::assertSelectorExists('[data-ad-code-preview]');
        self::assertSelectorNotExists('main iframe');
    }

    public function testAllContentLinksAndReadOnlyRoutes(): void
    {
        $client = self::createClient(['debug' => false]);
        $links = [];
        foreach (self::paths() as $path) {
            $crawler = $client->request('GET', $path);
            self::assertResponseIsSuccessful();
            $links = array_merge($links, $crawler->filter('a[href^="/"]')->each(static fn (Crawler $node): string => explode('#', $node->attr('href') ?? '')[0]));
            if ('/admin/media' === $path) {
                continue;
            }
            $client->request('POST', $path);
            self::assertResponseStatusCodeSame(405);
        }
        foreach (array_unique($links) as $path) {
            if (preg_match('#^/admin/(articles|categories|tags)/\d+#', $path)) {
                continue;
            }
            $client->request('GET', $path);
            self::assertResponseIsSuccessful('Broken content link: '.$path);
        }
        foreach (['pages', 'menus', 'advertisements'] as $module) {
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
