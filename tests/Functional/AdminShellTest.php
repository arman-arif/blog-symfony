<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Mock\MockDataProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\TerminableInterface;

final class AdminShellTest extends AdminTestCase
{
    /** @return iterable<string, array{string, int}> */
    public static function routes(): iterable
    {
        foreach (['/admin', '/admin/articles', '/admin?state=loading', '/admin?state=empty', '/admin?state=error', '/admin/articles?state=loading', '/admin/articles?state=empty', '/admin/articles?state=error'] as $path) {
            yield $path => [$path, 200];
        }
        yield 'no permission' => ['/admin/no-permission', 403];
    }

    #[DataProvider('routes')]
    public function testAdminRoutes(string $path, int $status): void
    {
        $client = self::createClient();
        $client->request('GET', $path);
        self::assertResponseStatusCodeSame($status);
        self::assertSelectorExists('main#main-content h1');
        self::assertSelectorExists('nav[aria-label="Admin navigation"]');
        self::assertSelectorExists('#admin-mobile-navigation');
        self::assertSelectorTextContains('main [role="status"]', 'Forms never save');
    }

    public function testDashboardChartsHaveAccessibleLocalData(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin');
        self::assertSelectorCount(2, 'canvas[role="img"][aria-describedby]');
        self::assertSelectorCount(7, '#views-chart-table tbody tr');
        self::assertSelectorCount(6, '#categories-chart-table tbody tr');
        self::assertSelectorCount(5, 'main ol li');
        self::assertSelectorExists('form[action="/admin/articles"] input[name="q"]');
        self::assertSelectorExists('#admin-quick-draft fieldset[disabled]');
        self::assertSelectorExists('[data-sidebar-collapse][aria-expanded="true"]');
        self::assertSelectorExists('nav[aria-label="Admin navigation"] a[aria-current="page"][href="/admin"]');
        self::assertSelectorExists('script[type="importmap"]');
        self::assertSelectorNotExists('script[src^="https://"]');
    }

    public function testReusableTableHasFiltersAndSafeMockActions(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/articles?q=notebook&status=draft');
        self::assertSelectorCount(38, '#articles-table [data-row]');
        self::assertSelectorCount(5, '#articles-table th[aria-sort="none"]');
        self::assertSelectorExists('[data-filter="status"] option[value="pending_review"]');
        self::assertSelectorExists('[data-filter="category"] option[value="architecture"]');
        self::assertSelectorExists('[data-filter="author"] option[value="maya-chen"]');
        self::assertSelectorExists('[data-select-all][disabled]');
        self::assertSelectorExists('[data-bulk-remove][data-component="confirm"]');
        self::assertSelectorCount(38, '[data-row-remove]');
        self::assertSelectorExists('[data-table-no-results][hidden]');
        self::assertSelectorExists('[data-table-error][hidden]');
        self::assertSelectorExists('[data-table-loading][hidden]');
        self::assertSelectorExists('#confirmation-dialog');
        self::assertSelectorExists('nav[aria-label="Admin navigation"] a[aria-current="page"][href="/admin/articles"]');
        $client->request('POST', '/admin/articles');
        self::assertResponseStatusCodeSame(405);
    }

    public function testEveryEnabledAdminLinkResolves(): void
    {
        $client = self::createClient();
        $links = [];
        foreach (['/admin', '/admin/articles'] as $path) {
            $crawler = $client->request('GET', $path);
            self::assertResponseIsSuccessful();
            $links = array_merge($links, $crawler->filter('a[href^="/"]')->each(static fn (Crawler $node): string => explode('#', $node->attr('href') ?? '')[0]));
        }
        foreach (array_unique($links) as $link) {
            $client->request('GET', $link);
            self::assertResponseIsSuccessful('Broken admin link: '.$link);
        }
    }

    public function testDashboardCountsComeFromMockRecords(): void
    {
        $data = new MockDataProvider();
        $dashboard = $data->dashboard();
        self::assertSame(30, $dashboard['statuses']['published']);
        self::assertSame(2, $dashboard['statuses']['draft']);
        self::assertSame(2, $dashboard['statuses']['scheduled']);
        self::assertSame(array_sum(array_column($data->articles(), 'viewCount')), $dashboard['stats'][0]['value']);
        self::assertSame(3, $dashboard['stats'][3]['value']);
        self::assertCount(5, $dashboard['activity']);
        self::assertSame(100, array_sum($dashboard['categories']['values']));
    }

    public function testAdminRequiresAuthentication(): void
    {
        self::ensureKernelShutdown();
        $kernel = self::bootKernel();
        self::assertInstanceOf(TerminableInterface::class, $kernel);
        $request = Request::create('/admin');
        $response = $kernel->handle($request);
        self::assertSame(302, $response->getStatusCode());
        self::assertStringContainsString('/login', (string) $response->headers->get('Location'));
        $kernel->terminate($request, $response);
    }

    public function testProductionAdminRequiresAuthentication(): void
    {
        $kernel = self::bootKernel(['environment' => 'prod', 'debug' => false]);
        self::assertInstanceOf(TerminableInterface::class, $kernel);
        foreach (['/admin', '/admin/articles', '/admin/no-permission'] as $path) {
            $request = Request::create($path);
            $response = $kernel->handle($request);
            self::assertSame(302, $response->getStatusCode(), $path);
            self::assertStringContainsString('/login', (string) $response->headers->get('Location'));
            $kernel->terminate($request, $response);
        }
    }
}
