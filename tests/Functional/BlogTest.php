<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Mock\MockDataProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class BlogTest extends WebTestCase
{
    #[DataProvider('filterCases')]
    public function testFilteringAndPagination(string $query, int $count): void
    {
        $client = self::createClient();
        $client->request('GET', '/blog'.$query);
        self::assertResponseIsSuccessful();
        self::assertSelectorCount($count, '[data-results] > article');
    }

    /** @return iterable<string, array{string, int}> */
    public static function filterCases(): iterable
    {
        yield 'default page' => ['', 9];
        yield 'category' => ['?category=design', 5];
        yield 'author' => ['?author=maya-chen', 5];
        yield 'tag' => ['?tag=creativity', 3];
        yield 'combined facets' => ['?category=design&tag=creativity', 1];
        yield 'search' => ['?q=quiet%20power', 1];
        yield 'empty' => ['?q=impossible-match', 0];
        yield 'second page' => ['?page=2', 9];
        yield 'negative page clamps' => ['?page=-1', 9];
        yield 'last page clamps' => ['?page=999', 3];
    }

    public function testOldestSortAndPaginationKeepFilters(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/blog?sort=oldest&tag=creativity');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Digital craft', $crawler->filter('[data-results] h3')->first()->text());
        $crawler = $client->request('GET', '/blog?q=the&sort=oldest');
        self::assertStringContainsString('sort=oldest', $crawler->filter('nav[aria-label="Pagination"] a')->last()->attr('href') ?? '');
        self::assertStringContainsString('q=the', $crawler->filter('nav[aria-label="Pagination"] a')->last()->attr('href') ?? '');
    }

    public function testArticleStatesAndPrivateContent(): void
    {
        $client = self::createClient();
        $articles = (new MockDataProvider())->articles();
        $client->request('GET', '/blog/'.$articles[1]['slug']);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-toc]');
        self::assertSelectorExists('[data-article-content] table');
        self::assertSelectorExists('[data-comment-form] textarea[required]');
        self::assertSelectorCount(2, '[data-comment-id]');
        self::assertSelectorExists('nav[aria-label="Previous and next stories"]');
        $client->request('GET', '/blog/'.$articles[0]['slug']);
        self::assertSelectorNotExists('[data-comment-form]');
        self::assertSelectorTextContains('[role="status"]', 'Comments are closed');
        foreach (array_slice($articles, 30) as $article) {
            $client->request('GET', '/blog/'.$article['slug']);
            self::assertResponseStatusCodeSame(404);
        }
        $client->request('GET', '/blog/does-not-exist');
        self::assertResponseStatusCodeSame(404);
    }
}
