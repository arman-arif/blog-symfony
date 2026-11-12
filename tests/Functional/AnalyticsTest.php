<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\ArticleView;
use App\Repository\ArticleRepository;
use App\Repository\UserRepository;
use App\Service\Analytics\AnalyticsRollupService;
use App\Service\Analytics\AnalyticsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final class AnalyticsTest extends FrontTestCase
{
    public function testRollupIsIdempotentAndPowersAnalyticsAndDashboard(): void
    {
        $client = self::createClient();
        $this->seed();
        $articles = self::getContainer()->get(ArticleRepository::class)->findBy([], ['id' => 'ASC'], 2);
        self::assertCount(2, $articles);
        [$first, $second] = $articles;
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $today = new \DateTimeImmutable('today 10:00');
        $yesterday = $today->modify('-1 day');

        foreach ([[$first, 3, $today], [$first, 2, $yesterday], [$second, 1, $today]] as [$article, $count, $day]) {
            for ($index = 0; $index < $count; ++$index) {
                $em->persist((new ArticleView())->setArticle($article)->setVisitorHash(hash('sha256', $article->getId().'-'.$index.'-'.$day->format('c')))->setViewedAt($day));
            }
        }
        $em->flush();

        $rollup = self::getContainer()->get(AnalyticsRollupService::class);
        self::assertSame(3, $rollup->aggregateRecent(2));
        $rollup->aggregateRecent(2);
        self::getContainer()->get(TagAwareCacheInterface::class)->invalidateTags(['analytics']);

        $from = $yesterday->format('Y-m-d');
        $to = $today->format('Y-m-d');
        $report = self::getContainer()->get(AnalyticsService::class)->report($yesterday, $today);
        self::assertSame(6, $report['total_views']);
        self::assertSame(5, $report['articles'][0]['views']);
        self::assertSame($first->getId(), $report['articles'][0]['id']);
        self::assertCount(2, $report['daily']['values']);

        $admin = self::getContainer()->get(UserRepository::class)->findOneByEmail('writer1@example.test');
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/admin/analytics?from='.$from.'&to='.$to);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', $first->getTitle());
        self::assertSelectorExists('#analytics-from[value="'.$from.'"]');

        $client->request('GET', '/admin/analytics/export?from='.$from.'&to='.$to);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('text/csv', (string) $client->getResponse()->headers->get('Content-Type'));
        self::assertStringContainsString($first->getTitle(), (string) $client->getResponse()->getContent());

        $client->request('GET', '/admin');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-dashboard-stat]');
        self::assertSelectorTextContains('main', '6');
    }

    public function testRollupPrunesOldRawViewsButKeepsStats(): void
    {
        $client = self::createClient();
        $this->seed();
        $article = self::getContainer()->get(ArticleRepository::class)->findOneBy(['slug' => 'the-quiet-power-of-making-things-with-intention']);
        self::assertNotNull($article);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist((new ArticleView())->setArticle($article)->setVisitorHash('old-view')->setViewedAt(new \DateTimeImmutable('-60 days')));
        $em->flush();

        $rollup = self::getContainer()->get(AnalyticsRollupService::class);
        $rollup->aggregateRecent(2);
        $pruned = $rollup->pruneRawViews(30);
        self::assertSame(1, $pruned);
        self::assertSame(0, (int) self::getContainer()->get(EntityManagerInterface::class)->getConnection()->fetchOne('SELECT COUNT(*) FROM article_view'));
    }

    public function testDateRangeIsClampedAndInvalidInputFallsBack(): void
    {
        $client = self::createClient();
        $this->seed();
        $admin = self::getContainer()->get(UserRepository::class)->findOneByEmail('writer1@example.test');
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/admin/analytics?from=not-a-date&to=also-not-a-date');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#analytics-from');
        self::assertSelectorExists('#analytics-to');

        $client->request('GET', '/admin/analytics?from=2020-01-01&to='.date('Y-m-d'));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', '366 days in range');
    }
}
