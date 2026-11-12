<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Mock\MockDataProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\TerminableInterface;
use Symfony\Component\Routing\RouterInterface;

final class PublicPagesTest extends FrontTestCase
{
    /** @return iterable<string, array{string, int}> */
    public static function pages(): iterable
    {
        foreach (['about', 'contact', 'privacy', 'terms', 'cookies', 'authors', 'authors/maya-chen', 'categories', 'categories/design', 'categories/architecture', 'tags', 'tags/creativity', 'search?q=design'] as $path) {
            yield $path => ['/'.$path, 200];
        }
        yield 'missing author' => ['/authors/missing', 404];
        yield 'missing category' => ['/categories/missing', 404];
        yield 'missing tag' => ['/tags/missing', 404];
        yield '404 preview' => ['/_error/404', 404];
        yield '500 preview' => ['/_error/500', 500];
        yield 'generic error preview' => ['/_error/403', 403];
        yield 'maintenance preview' => ['/_maintenance', 503];
    }

    #[DataProvider('pages')]
    public function testPublicPages(string $path, int $status): void
    {
        $client = self::createClient(['debug' => false]);
        $this->seed();
        $client->request('GET', $path);
        self::assertResponseStatusCodeSame($status);
        self::assertSelectorExists('main#main-content h1');
    }

    public function testContactAndCookieStates(): void
    {
        $client = self::createClient();
        $this->seed();
        $client->request('GET', '/contact');
        self::assertSelectorExists('#contact-email[type="email"][required]');
        self::assertSelectorExists('#contact-message[minlength="20"]');
        self::assertSelectorExists('fieldset[data-preview-fields][disabled]');
        self::assertSelectorExists('form noscript');
        self::assertSelectorTextContains('[role="status"]', 'No message is delivered');
        self::assertSelectorExists('[data-consent-choice="essential"]');
        $client->request('GET', '/_maintenance');
        self::assertResponseHeaderSame('Retry-After', '3600');
    }

    public function testAllPublicPreviewFormsWaitForClientEnhancement(): void
    {
        $client = self::createClient();
        $this->seed();
        $article = (new MockDataProvider())->articles()[1];
        foreach (['/', '/contact', '/blog/'.$article['slug']] as $path) {
            $crawler = $client->request('GET', $path);
            self::assertResponseIsSuccessful();
            $forms = $crawler->filter('form[data-component="validate"]');
            $previewForms = 0;
            $forms->each(static function (Crawler $form) use (&$previewForms): void {
                if (0 === $form->filter('fieldset[data-preview-fields]')->count()) {
                    return;
                }
                ++$previewForms;
                self::assertCount(1, $form->filter('fieldset[data-preview-fields][disabled]'));
                self::assertCount(1, $form->filter('noscript'));
            });
        }
    }

    /** @return iterable<string, array{string, int, string}> */
    public static function archives(): iterable
    {
        yield 'author' => ['/authors/maya-chen', 5, 'Maya Chen'];
        yield 'category' => ['/categories/design', 5, 'Design'];
        yield 'tag' => ['/tags/creativity', 3, '#Creativity'];
        yield 'empty child category' => ['/categories/architecture', 0, 'Architecture'];
    }

    #[DataProvider('archives')]
    public function testArchiveContentAndBoundedPages(string $path, int $count, string $heading): void
    {
        $client = self::createClient();
        $this->seed();
        $client->request('GET', $path.'?page=999');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('main h1', $heading);
        self::assertSelectorCount($count, 'main article');
        if (0 === $count) {
            self::assertSelectorTextContains('main', 'New stories are taking shape');
        }
    }

    public function testDiscoveryLinksAndPublicNavigationResolve(): void
    {
        $client = self::createClient();
        $this->seed();
        $links = [];
        foreach (['/about', '/contact', '/privacy', '/terms', '/cookies', '/authors', '/categories', '/tags', '/search'] as $path) {
            $crawler = $client->request('GET', $path);
            self::assertResponseIsSuccessful();
            $links = array_merge($links, $crawler->filter('a[href^="/"]')->each(static fn (Crawler $node): string => $node->attr('href') ?? ''));
        }
        foreach (array_unique($links) as $link) {
            $client->request('GET', $link);
            self::assertResponseIsSuccessful('Broken public page link: '.$link);
        }
    }

    public function testSearchPreservesItsRouteAndEscapesInput(): void
    {
        $client = self::createClient();
        $this->seed();
        $crawler = $client->request('GET', '/search?q=the&sort=oldest');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(9, '[data-results] > article');
        self::assertSelectorExists('form[action="/search"]');
        $next = $crawler->filter('nav[aria-label="Pagination"] a')->last()->attr('href') ?? '';
        self::assertStringStartsWith('/search?', $next);
        self::assertStringContainsString('q=the', $next);
        self::assertStringContainsString('sort=oldest', $next);
        $client->request('GET', $next);
        self::assertResponseIsSuccessful();
        $client->request('GET', '/search', ['q' => '<script>alert("preview")</script>']);
        self::assertSelectorTextContains('main', 'No matches this time');
        self::assertSelectorTextContains('main', '<script>alert("preview")</script>');
        self::assertSelectorNotExists('main script');
        self::assertSelectorExists('main a[href="/search"]');
    }

    public function testRealNotFoundUsesBrandedPage(): void
    {
        $client = self::createClient(['debug' => false]);
        $client->request('GET', '/a-path-that-does-not-exist');
        self::assertResponseStatusCodeSame(404);
        self::assertSelectorTextSame('main h1', 'This path leads somewhere else.');
        self::assertSelectorNotExists('.exception-message');
    }

    public function testPreviewsAreUnavailableInProduction(): void
    {
        $kernel = self::bootKernel(['environment' => 'prod', 'debug' => false]);
        self::assertInstanceOf(TerminableInterface::class, $kernel);
        $router = $kernel->getContainer()->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);
        self::assertNull($router->getRouteCollection()->get('_preview_error'));
        foreach (['/_maintenance', '/_error/500'] as $path) {
            $request = Request::create($path);
            $response = $kernel->handle($request);
            self::assertSame(404, $response->getStatusCode());
            $kernel->terminate($request, $response);
        }
    }
}
