<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Article;
use App\Entity\ArticleDailyStat;
use App\Entity\ArticleView;
use App\Enum\ArticleStatus;
use App\Enum\ArticleVisibility;
use App\Repository\ArticleDailyStatRepository;
use App\Repository\ArticleRepository;
use App\Repository\ArticleViewRepository;
use App\Service\Analytics\AnalyticsRollupService;
use App\Service\Article\PublicCatalog;
use App\Tests\Factory\ArticleFactory;
use App\Tests\Factory\CategoryFactory;
use App\Tests\Factory\TagFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;

final class PublicDiscoveryRegressionTest extends AdminTestCase
{
    /** @return iterable<string, array{string}> */
    public static function hiddenKinds(): iterable
    {
        foreach (['private', 'premium', 'draft', 'scheduled', 'future', 'deleted'] as $kind) {
            yield $kind => [$kind];
        }
    }

    #[DataProvider('hiddenKinds')]
    public function testHiddenArticlesAreExcludedFromEveryPublicQueryAndHttpSurface(string $kind): void
    {
        $client = self::createClient();
        $category = CategoryFactory::createOne();
        $tag = TagFactory::createOne();
        $article = $this->story('Secret privacyneedle '.$kind);
        $article->addCategory($category)->addTag($tag);
        match ($kind) {
            'private' => $article->setVisibility(ArticleVisibility::Private),
            'premium' => $article->setIsPremium(true),
            'draft' => $article->setStatus(ArticleStatus::Draft),
            'scheduled' => $article->setStatus(ArticleStatus::Scheduled),
            'future' => $article->setPublishedAt(new \DateTimeImmutable('+1 day')),
            'deleted' => $article->setDeletedAt(new \DateTimeImmutable()),
            default => throw new \InvalidArgumentException('Unknown hidden article case: '.$kind),
        };
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->flush();
        self::getContainer()->get('cache.content')->invalidateTags(['articles', 'homepage', 'sitemap']);
        $slug = $article->getSlug();
        $title = $article->getTitle();
        $repository = self::getContainer()->get(ArticleRepository::class);
        self::assertNull($repository->findOnePublishedBySlug($slug));
        self::assertSame([], $repository->findPublished());
        self::assertSame([], $repository->findPublishedByIds([$article->getId()]));
        self::assertSame([], $repository->mostViewedPublished());
        self::assertSame([], $repository->searchPublishedIds('privacyneedle'));
        self::assertSame([], $repository->publishedCountsByCategory());
        self::assertSame([], $repository->publishedCountsByTag());
        self::assertSame([], $repository->publishedCountsByAuthor());

        $client->request('GET', '/blog/'.$slug);
        self::assertResponseStatusCodeSame(404);
        foreach (['/', '/blog', '/search?q=privacyneedle', '/feed.xml', '/feed/category/'.$category->getSlug().'.xml', '/sitemap-articles.xml'] as $path) {
            $client->request('GET', $path);
            self::assertResponseIsSuccessful($path);
            self::assertStringNotContainsString($title, (string) $client->getResponse()->getContent(), $path);
            self::assertStringNotContainsString('/blog/'.$slug, (string) $client->getResponse()->getContent(), $path);
        }
    }

    public function testFilteredListsAndLimitedRelatedPopularAdjacentQueriesHydrateCompleteCollections(): void
    {
        self::createClient();
        $categories = CategoryFactory::createMany(2);
        $tags = TagFactory::createMany(5);
        $origin = $this->story('Origin story', '-1 hour');
        $origin->addCategory($categories[0])->addTag($tags[0]);
        for ($index = 1; $index <= 5; ++$index) {
            $story = $this->story('Related story '.$index, '-'.$index.' minutes');
            foreach ($categories as $category) {
                $story->addCategory($category);
            }
            foreach ($tags as $tag) {
                $story->addTag($tag);
            }
        }
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->flush();
        $originId = $origin->getId();
        $em->clear();
        $repository = self::getContainer()->get(ArticleRepository::class);
        $origin = $repository->find($originId);
        $related = $repository->findRelated($origin, 3);
        self::assertCount(3, $related);
        foreach ($related as $story) {
            self::assertCount(5, $story->getTags());
            self::assertCount(2, $story->getCategories());
        }
        $em->clear();
        $popular = $repository->mostViewedPublished(3);
        self::assertCount(3, $popular);
        self::assertCount(5, $popular[0]->getTags());
        self::assertCount(2, $popular[0]->getCategories());
        $em->clear();
        $result = $repository->paginatePublished(['category' => $categories[1]->getSlug(), 'tag' => $tags[0]->getSlug()], 1, 3);
        self::assertSame(5, $result['total']);
        self::assertCount(3, $result['items']);
        self::assertCount(5, $result['items'][0]->getTags());
        self::assertCount(2, $result['items'][0]->getCategories());
        $em->clear();
        $admin = $repository->findForAdmin(['category' => $categories[1]->getId()], 1, 3);
        self::assertSame(5, $admin['total']);
        self::assertCount(2, $admin['items'][0]->getCategories());
        $em->clear();
        $origin = $repository->find($originId);
        $next = $repository->findNextPublished($origin);
        self::assertNotNull($next);
        self::assertCount(5, $next->getTags());
        self::assertCount(2, $next->getCategories());
        $latest = $repository->findPublished(1)[0];
        $em->clear();
        $previous = $repository->findPreviousPublished($repository->find($latest->getId()));
        self::assertNotNull($previous);
        self::assertCount(5, $previous->getTags());
        self::assertCount(2, $previous->getCategories());
    }

    public function testRelatedAndRecommendedPreferGreaterTaxonomyOverlapOverRecency(): void
    {
        self::createClient();
        $category = CategoryFactory::createOne();
        $tags = TagFactory::createMany(3);
        $hero = $this->story('Newest hero', '-1 minute');
        $hero->addCategory($category);
        foreach ($tags as $tag) {
            $hero->addTag($tag);
        }
        $weak = $this->story('Newer weak match', '-2 minutes');
        $weak->addTag($tags[0]);
        $strong = $this->story('Older strongest match', '-1 day');
        $strong->addCategory($category);
        foreach ($tags as $tag) {
            $strong->addTag($tag);
        }
        $this->story('Unrelated recent filler', '-3 minutes');
        self::getContainer()->get(EntityManagerInterface::class)->flush();
        self::getContainer()->get('cache.content')->invalidateTags(['articles', 'homepage']);
        $related = self::getContainer()->get(ArticleRepository::class)->findRelated($hero, 3);
        self::assertSame([$strong->getId(), $weak->getId()], array_map(static fn (Article $story): ?int => $story->getId(), $related));
        $homepage = self::getContainer()->get(PublicCatalog::class)->homepage();
        self::assertSame([$strong->getId(), $weak->getId()], array_map(static fn (Article $story): ?int => $story->getId(), $homepage['recommended']));
        self::assertSame([], $homepage['popular'], 'No activity must not be presented as a lifetime ranking.');
        self::assertSame([], $homepage['trending'], 'No activity must not be presented as trending.');
    }

    public function testViewRankingsFilterBeforeLimitingAndTrendingUsesRecentVelocity(): void
    {
        self::createClient();
        $steady = $this->story('Steady popular story');
        $rising = $this->story('Fast rising story');
        $hidden = $this->story('Hidden high traffic story')->setVisibility(ArticleVisibility::Private);
        $premium = $this->story('Premium high traffic story')->setIsPremium(true);
        $historical = $this->story('Historical traffic story');
        $this->views($steady, 60, '-2 days');
        $this->views($steady, 6, '-2 hours');
        $this->views($rising, 12, '-2 hours');
        $this->views($hidden, 80, '-2 hours');
        $this->views($premium, 80, '-2 hours');
        $this->views($historical, 100, '-31 days');
        self::getContainer()->get(EntityManagerInterface::class)->flush();
        $repository = self::getContainer()->get(ArticleViewRepository::class);
        self::assertSame([$steady->getId(), $rising->getId()], $repository->topArticleIdsSince(new \DateTimeImmutable('-30 days'), 2));
        self::assertSame([$rising->getId(), $steady->getId()], $repository->trendingArticleIds(new \DateTimeImmutable(), 2));
        self::getContainer()->get(AnalyticsRollupService::class)->aggregateRecent(30);
        self::getContainer()->get('cache.content')->invalidateTags(['articles', 'homepage']);
        $homepage = self::getContainer()->get(PublicCatalog::class)->homepage();
        self::assertSame([$steady->getId(), $rising->getId()], array_map(static fn (Article $story): ?int => $story->getId(), $homepage['popular']));
        self::assertSame([$rising->getId(), $steady->getId()], array_map(static fn (Article $story): ?int => $story->getId(), $homepage['trending']));
    }

    private function story(string $title, string $published = '-1 day'): Article
    {
        return ArticleFactory::createOne(['author' => self::authenticatedUser(), 'title' => $title, 'status' => ArticleStatus::Published, 'publishedAt' => new \DateTimeImmutable($published)]);
    }

    public function testPopularityUsesRetainedRollupsAndRefreshesAfterAggregation(): void
    {
        self::createClient();
        $public = $this->story('Retained public popularity');
        $old = $this->story('Old rollup');
        $hidden = $this->story('Hidden rollup')->setVisibility(ArticleVisibility::Private);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        foreach ([[$public, '-29 days', 12], [$old, '-30 days', 900], [$hidden, 'today', 1000], [$old, 'tomorrow', 1000]] as [$article, $day, $views]) {
            $em->persist((new ArticleDailyStat())->setArticle($article)->setStatDate(new \DateTimeImmutable($day))->setViews($views));
        }
        $em->flush();
        $stats = self::getContainer()->get(ArticleDailyStatRepository::class);
        self::assertSame([$public->getId()], $stats->popularPublicIds(new \DateTimeImmutable(), 1));
        self::getContainer()->get('cache.content')->invalidateTags(['homepage']);
        $catalog = self::getContainer()->get(PublicCatalog::class);
        self::assertSame($public->getId(), $catalog->homepage()['popular'][0]->getId());
        $this->views($old, 20, '-1 hour');
        $em->flush();
        self::getContainer()->get(AnalyticsRollupService::class)->aggregateRecent();
        self::assertSame($old->getId(), $catalog->homepage()['popular'][0]->getId());
    }

    private function views(Article $article, int $count, string $when): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        for ($index = 0; $index < $count; ++$index) {
            $em->persist((new ArticleView())->setArticle($article)->setViewedAt(new \DateTimeImmutable($when))->setVisitorHash(hash('sha256', $article->getId().':'.$when.':'.$index)));
        }
    }
}
