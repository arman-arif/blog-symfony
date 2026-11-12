<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Mock\MockDataProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\TerminableInterface;

final class ArticleEditorTest extends AdminTestCase
{
    /** @return iterable<string, array{string, int}> */
    public static function pages(): iterable
    {
        foreach (['/admin/articles/new', '/admin/articles/1/edit', '/admin/articles/31/edit', '/admin/articles/33/edit', '/admin/articles/37/edit', '/admin/articles/31/preview', '/admin/articles/31/revisions', '/admin/articles/31/revisions/311/compare', '/admin/articles/31/revisions/313/compare'] as $path) {
            yield $path => [$path, 200];
        }
        foreach (['/admin/articles/999/edit', '/admin/articles/999/preview', '/admin/articles/999/revisions', '/admin/articles/31/revisions/999/compare', '/admin/articles/31/revisions/11/compare'] as $path) {
            yield $path => [$path, 404];
        }
    }

    #[DataProvider('pages')]
    public function testEditorRoutes(string $path, int $status): void
    {
        $client = self::createClient(['debug' => false]);
        $client->request('GET', $path);
        self::assertResponseStatusCodeSame($status);
        self::assertSelectorExists('main h1');
    }

    public function testFormHasCompleteEditorAndEntityShapedSettings(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/articles/31/edit');
        self::assertSelectorExists('#article-form[data-component="validate article-form"] fieldset[disabled]');
        self::assertSelectorExists('#article-title[value="An unfinished notebook of ideas"][required]');
        self::assertSelectorExists('#article-author option[value="1"][selected]');
        self::assertSelectorExists('#article-category-1[checked]');
        self::assertSelectorCount(8, 'input[name="categories[]"]');
        self::assertSelectorExists('#article-category-7');
        self::assertSelectorExists('#article-tags-input[list="article-tags-options"]');
        self::assertSelectorCount(25, '#article-tags-options option');
        self::assertSelectorExists('#article-visibility option[value="private"]');
        self::assertSelectorExists('#article-status option[value="draft"][selected]');
        self::assertSelectorExists('#article-allow-comments[role="switch"]');
        self::assertSelectorExists('#article-seo-title[maxlength="70"]');
        self::assertSelectorExists('#article-seo-description[maxlength="160"]');
        self::assertSelectorExists('#article-focus-keyword');
        self::assertSelectorExists('#article-canonical[type="url"]');
        self::assertSelectorExists('#article-noindex');
        self::assertSelectorExists('[data-snippet-title]');
        self::assertSelectorExists('[data-autosave-status][role="status"]');
        self::assertSelectorExists('[data-live-preview]');
        self::assertSelectorExists('#article-live-preview');
        foreach (['bold', 'italic', 'underline', 'blockquote', 'code-block'] as $format) {
            self::assertSelectorExists('[data-editor-format="'.$format.'"]');
        }
        foreach (['link', 'image', 'table', 'video', 'divider', 'undo', 'redo'] as $action) {
            self::assertSelectorExists('[data-editor-action="'.$action.'"]');
        }
        self::assertSelectorExists('[data-editor-heading]');
        self::assertSelectorExists('[data-editor-align]');
        self::assertSelectorExists('[data-editor-list="bullet"]');
        self::assertSelectorExists('[data-editor-list="ordered"]');
        self::assertSelectorExists('#editor-link-form fieldset[disabled]');
        self::assertSelectorExists('#editor-table-rows[min="1"][max="6"]');
        self::assertSelectorExists('#editor-video-form fieldset[disabled]');
        self::assertSelectorCount(6, '[data-media-select]');
        self::assertSelectorExists('[data-media-insert][disabled]');
        self::assertSelectorNotExists('main iframe');
        self::assertSelectorNotExists('#article-form form');
    }

    public function testEmptyDraftAndScheduleValues(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/articles/new');
        self::assertSelectorExists('#article-title[value=""]');
        self::assertSelectorExists('#article-slug[value=""]');
        self::assertSelectorExists('[data-featured-image][hidden]');
        self::assertSelectorExists('#article-content p br');
        $client->request('GET', '/admin/articles/33/edit');
        self::assertSelectorExists('#article-status option[value="scheduled"][selected]');
        self::assertSelectorExists('#article-scheduled-at[value="2026-12-01T00:00"]');
        self::assertSelectorNotExists('[data-schedule-panel][hidden]');
        self::assertSelectorExists('#article-author option[value="3"][selected]');
    }

    public function testTabsTrashAndRevisionDiffStates(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/articles');
        self::assertSelectorCount(6, '[data-table-tab]');
        self::assertSelectorCount(2, '[data-row][data-trash="true"]');
        self::assertSelectorExists('[data-apply-bulk-status]');
        self::assertSelectorExists('[data-bulk-restore]');
        self::assertSelectorExists('a[href="/admin/articles/new"]');
        $client->request('GET', '/admin/articles/31/revisions');
        self::assertSelectorCount(3, 'main table tbody tr');
        self::assertSelectorTextContains('main', 'Autosave example');
        $client->request('GET', '/admin/articles/31/revisions/311/compare');
        self::assertSelectorCount(2, 'main del');
        self::assertSelectorCount(2, 'main ins');
        self::assertSelectorExists('main button[disabled]');
        $client->request('GET', '/admin/articles/31/revisions/313/compare');
        self::assertSelectorNotExists('main del');
        self::assertSelectorNotExists('main ins');
    }

    public function testAllEditorLinksResolveAndFormsCannotPost(): void
    {
        $client = self::createClient();
        $links = [];
        foreach (['/admin/articles/new', '/admin/articles/31/edit', '/admin/articles/31/preview', '/admin/articles/31/revisions', '/admin/articles/31/revisions/311/compare'] as $path) {
            $crawler = $client->request('GET', $path);
            self::assertResponseIsSuccessful();
            $links = array_merge($links, $crawler->filter('a[href^="/"]')->each(static fn (Crawler $node): string => explode('#', $node->attr('href') ?? '')[0]));
        }
        foreach (array_unique($links) as $path) {
            $client->request('GET', $path);
            self::assertResponseIsSuccessful('Broken editor link: '.$path);
        }
        foreach (['/admin/articles/new', '/admin/articles/31/edit'] as $path) {
            $client->request('POST', $path);
            self::assertResponseStatusCodeSame(405);
        }
    }

    public function testEditorRoutesRequireAuthenticationInProduction(): void
    {
        $kernel = self::bootKernel(['environment' => 'prod', 'debug' => false]);
        self::assertInstanceOf(TerminableInterface::class, $kernel);
        foreach (['/admin/articles/new', '/admin/articles/31/edit', '/admin/articles/31/preview', '/admin/articles/31/revisions', '/admin/articles/31/revisions/311/compare'] as $path) {
            $request = Request::create($path);
            $response = $kernel->handle($request);
            self::assertSame(302, $response->getStatusCode());
            self::assertStringContainsString('/login', (string) $response->headers->get('Location'));
            $kernel->terminate($request, $response);
        }
    }

    public function testRevisionContractAndTrashDoNotChangePublicFixtures(): void
    {
        $data = new MockDataProvider();
        self::assertCount(36, $data->articles());
        self::assertCount(2, $data->trashedArticles());
        foreach ($data->revisions(31) as $revision) {
            self::assertSame(31, $revision['article']['id']);
            self::assertInstanceOf(\DateTimeImmutable::class, $revision['createdAt']);
            self::assertIsString($revision['content']);
        }
        self::assertSame([], $data->revisions(999));
        self::assertNull($data->adminArticle(999));
    }
}
