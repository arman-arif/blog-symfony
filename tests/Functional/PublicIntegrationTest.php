<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Article;
use App\Enum\ArticleStatus;
use App\Mock\MockDataProvider;
use App\Repository\ArticleRepository;
use App\Repository\UserRepository;
use App\Service\Article\ArticleService;

final class PublicIntegrationTest extends FrontTestCase
{
    public function testHomepageUsesPublishedContentAndInvalidatesItsCache(): void
    {
        $client = self::createClient();
        $this->seed();

        $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('The quiet power of making things with intention', (string) $client->getResponse()->getContent());

        $author = self::getContainer()->get(UserRepository::class)->findOneByEmail('writer1@example.test');
        self::assertNotNull($author);
        $article = (new Article())
            ->setTitle('A brand new homepage story')
            ->setExcerpt('Freshly published for the homepage test.')
            ->setContent('<p>'.str_repeat('A fresh story for curious readers. ', 10).'</p>')
            ->setAuthor($author);
        self::getContainer()->get(ArticleService::class)->create($article, $author, ArticleStatus::Published);

        $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('A brand new homepage story', (string) $client->getResponse()->getContent());
    }

    public function testNonPublicArticlesNeverAppearOnTheWebsite(): void
    {
        $client = self::createClient();
        $this->seed();
        $fixtures = (new MockDataProvider())->articles();

        foreach (array_slice($fixtures, 30) as $fixture) {
            $client->request('GET', '/blog/'.$fixture['slug']);
            self::assertResponseStatusCodeSame(404, 'Non-public fixture leaked: '.$fixture['slug']);
        }

        $published = self::getContainer()->get(ArticleRepository::class)->findOneBy(['slug' => 'the-quiet-power-of-making-things-with-intention']);
        self::assertNotNull($published);
        $user = self::getContainer()->get(UserRepository::class)->findOneByEmail('writer1@example.test');
        self::assertNotNull($user);
        self::getContainer()->get(ArticleService::class)->delete($published);

        $client->request('GET', '/blog/the-quiet-power-of-making-things-with-intention');
        self::assertResponseStatusCodeSame(404);
    }

    public function testFullTextSearchFindsArticleBodyContent(): void
    {
        $client = self::createClient();
        $this->seed();

        $crawler = $client->request('GET', '/blog?q=attention%20beginning');
        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('[data-results] > article')->count());

        $client->request('GET', '/search?q=attention%20beginning');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-results] > article');
    }

    public function testRssFeedsContainOnlyPublishedArticles(): void
    {
        $client = self::createClient();
        $this->seed();

        $client->request('GET', '/feed.xml');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/rss+xml; charset=UTF-8');
        $feed = simplexml_load_string((string) $client->getResponse()->getContent());
        self::assertNotFalse($feed);
        self::assertSame('Fieldnotes', (string) $feed->channel->title);
        self::assertCount(20, $feed->channel->item);
        self::assertStringNotContainsString('An unfinished notebook of ideas', (string) $client->getResponse()->getContent());

        $client->request('GET', '/feed/category/design.xml');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Fieldnotes · Design', (string) $client->getResponse()->getContent());

        $client->request('GET', '/feed/category/missing.xml');
        self::assertResponseStatusCodeSame(404);
    }

    public function testRelatedAndAdjacentArticlesAreRendered(): void
    {
        $client = self::createClient();
        $this->seed();

        $client->request('GET', '/blog/technology-that-gives-us-our-time-back');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('nav[aria-label="Previous and next stories"]');
        self::assertGreaterThanOrEqual(1, $client->getCrawler()->filter('nav[aria-label="Previous and next stories"] a')->count());
        self::assertSelectorTextContains('main', 'You might also enjoy');
    }
}
