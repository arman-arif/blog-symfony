<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Redirect;
use App\Repository\ArticleRepository;
use App\Repository\RedirectRepository;
use App\Repository\UserRepository;
use App\Service\Article\ArticleService;
use App\Tests\Support\InMemorySettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final class SeoTest extends FrontTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        InMemorySettingsService::resetOverride();
    }

    protected function tearDown(): void
    {
        InMemorySettingsService::resetOverride();
        parent::tearDown();
    }

    public function testHomepageMetadataIncludesSocialAndStructuredData(): void
    {
        $client = self::createClient();
        $this->seed();
        $client->request('GET', '/');

        $html = (string) $client->getResponse()->getContent();
        self::assertMatchesRegularExpression('#<link rel="canonical" href="http://localhost/">#', $html);
        self::assertStringContainsString('<meta property="og:type" content="website">', $html);
        self::assertStringContainsString('<meta name="twitter:card" content="summary">', $html);
        $graph = $this->jsonLdGraph($html);
        self::assertContains('Organization', array_column($graph, '@type'));
        self::assertContains('WebSite', array_column($graph, '@type'));
        $website = array_values(array_filter($graph, static fn (array $node): bool => 'WebSite' === $node['@type']))[0];
        self::assertSame('SearchAction', $website['potentialAction']['@type']);
    }

    public function testArticleMetadataAndBlogPostingSchema(): void
    {
        $client = self::createClient();
        $this->seed();
        $client->request('GET', '/blog/the-quiet-power-of-making-things-with-intention');

        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('<meta property="og:type" content="article">', $html);
        self::assertStringContainsString('article:published_time', $html);
        $graph = $this->jsonLdGraph($html);
        $types = array_column($graph, '@type');
        self::assertContains('BlogPosting', $types);
        self::assertContains('BreadcrumbList', $types);
        $article = array_values(array_filter($graph, static fn (array $node): bool => 'BlogPosting' === $node['@type']))[0];
        self::assertSame('The quiet power of making things with intention', $article['headline']);
        self::assertSame('Maya Chen', $article['author']['name']);
    }

    public function testSearchResultsAreNoindex(): void
    {
        $client = self::createClient();
        $this->seed();
        $client->request('GET', '/search?q=design');

        self::assertStringContainsString('<meta name="robots" content="noindex,follow">', (string) $client->getResponse()->getContent());
    }

    public function testSitemapIndexAndArticleSitemapExcludePrivateContent(): void
    {
        $client = self::createClient();
        $this->seed();

        $client->request('GET', '/sitemap.xml');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/xml; charset=UTF-8');
        $index = simplexml_load_string((string) $client->getResponse()->getContent());
        self::assertNotFalse($index);
        self::assertCount(5, $index->sitemap);

        $article = self::getContainer()->get(ArticleRepository::class)->findOneBy(['slug' => 'the-quiet-power-of-making-things-with-intention']);
        self::assertNotNull($article);
        $article->getSeo()->setNoindex(true);
        self::getContainer()->get(EntityManagerInterface::class)->flush();
        self::getContainer()->get(TagAwareCacheInterface::class)->invalidateTags(['articles', 'sitemap']);

        $client->request('GET', '/sitemap-articles.xml');
        $xml = simplexml_load_string((string) $client->getResponse()->getContent());
        self::assertNotFalse($xml);
        $flat = implode("\n", array_map(static fn ($url): string => (string) $url->loc, iterator_to_array($xml->url, false)));
        self::assertStringNotContainsString('the-quiet-power-of-making-things-with-intention', $flat);
        self::assertStringContainsString('/blog/technology-that-gives-us-our-time-back', $flat);
        self::assertStringNotContainsString('an-unfinished-notebook-of-ideas', $flat);
    }

    public function testRobotsTxtComesFromSettings(): void
    {
        $client = self::createClient();
        $this->seed();
        $client->request('GET', '/robots.txt');

        self::assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Disallow: /admin/', $content);
        self::assertStringContainsString('Sitemap: http://localhost/sitemap.xml', $content);
    }

    public function testRedirectsAreServedAndCountHits(): void
    {
        $client = self::createClient();
        $this->seed();
        $redirect = (new Redirect())
            ->setSourcePath('/old-journal')
            ->setTargetUrl('/blog/the-quiet-power-of-making-things-with-intention')
            ->setStatusCode(301);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($redirect);
        $em->flush();
        self::getContainer()->get(TagAwareCacheInterface::class)->invalidateTags(['redirects']);

        $client->request('GET', '/old-journal');
        self::assertResponseStatusCodeSame(301);
        self::assertStringContainsString('/blog/the-quiet-power-of-making-things-with-intention', (string) $client->getResponse()->headers->get('Location'));

        $em->clear();
        self::assertSame(1, self::getContainer()->get(RedirectRepository::class)->findOneBy(['sourcePath' => '/old-journal'])?->getHits());
    }

    public function testPublishingASlugChangeCreatesA301(): void
    {
        $client = self::createClient();
        $this->seed();
        $article = self::getContainer()->get(ArticleRepository::class)->findOneBy(['slug' => 'the-quiet-power-of-making-things-with-intention']);
        $admin = self::getContainer()->get(UserRepository::class)->findOneByEmail('writer1@example.test');
        self::assertNotNull($article);
        self::assertNotNull($admin);

        $oldSlug = $article->getSlug();
        $article->setSlug('a-renamed-seo-story');
        self::getContainer()->get(ArticleService::class)->update($article, $admin, null, false, $oldSlug);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $redirect = self::getContainer()->get(RedirectRepository::class)->findOneBy(['sourcePath' => '/blog/'.$oldSlug]);
        self::assertNotNull($redirect);
        self::assertSame('/blog/a-renamed-seo-story', $redirect->getTargetUrl());

        $client->request('GET', '/blog/'.$oldSlug);
        self::assertResponseStatusCodeSame(301);
    }

    public function testAnalyticsScriptRequiresConsent(): void
    {
        $client = self::createClient();
        $this->seed();
        InMemorySettingsService::setOverride('seo.ga_id', 'G-TEST123');

        $client->request('GET', '/');
        self::assertStringNotContainsString('googletagmanager.com', (string) $client->getResponse()->getContent());

        $client->getCookieJar()->set(new Cookie('fieldnotes_consent', 'all'));
        $client->request('GET', '/');
        self::assertStringContainsString('googletagmanager.com', (string) $client->getResponse()->getContent());
    }

    /** @return list<array<string, mixed>> */
    private function jsonLdGraph(string $html): array
    {
        self::assertSame(1, preg_match('#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', $html, $matches));
        $data = json_decode($matches[1], true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('https://schema.org', $data['@context']);

        return $data['@graph'];
    }
}
