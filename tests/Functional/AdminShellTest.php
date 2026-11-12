<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Factory\ArticleFactory;
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
        self::assertSelectorTextContains('main [role="status"]', 'Articles, revisions and publishing are live');
    }

    public function testDashboardChartsHaveAccessibleLocalData(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin');
        self::assertSelectorCount(2, 'canvas[role="img"][aria-describedby]');
        self::assertSelectorExists('#views-chart-table');
        self::assertSelectorExists('#categories-chart-table');
        self::assertSelectorCount(4, '[data-dashboard-stat]');
        self::assertSelectorExists('form[action="/admin/articles"] input[name="q"]');
        self::assertSelectorExists('#admin-quick-draft fieldset[disabled]');
        self::assertSelectorExists('[data-sidebar-collapse][aria-expanded="true"]');
        self::assertSelectorExists('nav[aria-label="Admin navigation"] a[aria-current="page"][href="/admin"]');
        self::assertSelectorExists('script[type="importmap"]');
        self::assertSelectorNotExists('script[src^="https://"]');
    }

    public function testArticleListSupportsFiltersAndPagination(): void
    {
        $client = self::createClient();
        $author = self::authenticatedUser();
        for ($index = 1; $index <= 12; ++$index) {
            ArticleFactory::createOne(['author' => $author, 'title' => 'Draft story '.$index]);
        }

        $client->request('GET', '/admin/articles?status=draft');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(10, 'tbody [data-row]');
        self::assertSelectorExists('#filter-q');
        self::assertSelectorExists('#filter-status option[value="pending_review"]');
        self::assertSelectorExists('#filter-author');
        self::assertSelectorExists('#filter-category');
        self::assertSelectorTextContains('main', 'Draft story');
        self::assertSelectorExists('nav[aria-label="Article pagination"] a[href*="page=2"]');
        self::assertSelectorExists('nav[aria-label="Admin navigation"] a[aria-current="page"][href="/admin/articles"]');

        $client->request('GET', '/admin/articles?q=Draft+story+12');
        self::assertSelectorCount(1, 'tbody [data-row]');
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
            if (preg_match('#^/admin/(articles|categories|tags)/\d+#', $link)) {
                continue;
            }
            $client->request('GET', $link);
            self::assertResponseIsSuccessful('Broken admin link: '.$link);
        }
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
