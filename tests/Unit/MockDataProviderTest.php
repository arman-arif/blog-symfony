<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Mock\MockDataProvider;
use PHPUnit\Framework\TestCase;

final class MockDataProviderTest extends TestCase
{
    public function testFixturesMatchTheContract(): void
    {
        $data = new MockDataProvider();
        self::assertCount(36, $data->articles());
        self::assertCount(8, $data->categories());
        self::assertCount(25, $data->tags());
        self::assertCount(6, $data->authors());
        self::assertCount(5, $data->roles());
        $slugs = [];
        foreach ($data->articles() as $article) {
            self::assertIsString($article['title']);
            self::assertIsArray($article['author']);
            self::assertArrayHasKey('displayName', $article['author']);
            self::assertInstanceOf(\DateTimeImmutable::class, $article['createdAt']);
            self::assertFileExists(dirname(__DIR__, 2).'/assets/'.$article['featuredImage']['url']);
            $slugs[] = $article['slug'];
        }
        self::assertCount(36, array_unique($slugs));
        self::assertCount(1, $data->comments()[0]['replies']);
        self::assertCount(5, $data->pages());
        foreach ($data->pages() as $slug => $page) {
            self::assertSame($slug, $page['slug']);
            self::assertArrayHasKey('content', $page);
            self::assertIsArray($page['sections']);
            self::assertInstanceOf(\DateTimeImmutable::class, $page['updatedAt']);
        }
    }
}
