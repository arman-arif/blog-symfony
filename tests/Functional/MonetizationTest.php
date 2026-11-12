<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Advertisement;
use App\Enum\AdvertisementPlacement;
use App\Enum\AdvertisementType;
use App\Repository\AdvertisementRepository;
use App\Repository\ArticleRepository;
use App\Repository\UserRepository;
use App\Service\Article\ArticleService;
use App\Service\Monetization\AdvertisementService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final class MonetizationTest extends FrontTestCase
{
    public function testAdsRenderByPlacementPriorityScheduleAndInvalidateCache(): void
    {
        $client = self::createClient();
        $this->seed();
        $client->request('GET', '/');
        self::assertStringNotContainsString('data-ad-marker', (string) $client->getResponse()->getContent());

        $service = self::getContainer()->get(AdvertisementService::class);
        $low = (new Advertisement())->setName('Low priority')->setPlacement(AdvertisementPlacement::Header)
            ->setType(AdvertisementType::Html)->setContent('<p data-ad-marker="low">Low priority ad</p>')->setPriority(1)->setIsActive(true);
        $service->save($low);
        $high = (new Advertisement())->setName('High priority')->setPlacement(AdvertisementPlacement::Header)
            ->setType(AdvertisementType::Html)->setContent('<p data-ad-marker="high">High priority ad</p>')->setPriority(100)->setIsActive(true)
            ->setStartsAt(new \DateTimeImmutable('-1 day'))->setEndsAt(new \DateTimeImmutable('+1 day'));
        $service->save($high);

        $client->request('GET', '/');
        self::assertStringContainsString('data-ad-marker="high"', (string) $client->getResponse()->getContent());
        self::assertStringNotContainsString('data-ad-marker="low"', (string) $client->getResponse()->getContent());

        $expired = (new Advertisement())->setName('Expired')->setPlacement(AdvertisementPlacement::Footer)
            ->setType(AdvertisementType::Html)->setContent('<p data-ad-marker="expired">Expired ad</p>')->setPriority(100)->setIsActive(true)
            ->setEndsAt(new \DateTimeImmutable('-1 day'));
        $service->save($expired);
        $client->request('GET', '/');
        self::assertStringNotContainsString('data-ad-marker="expired"', (string) $client->getResponse()->getContent());
    }

    public function testInArticleAdIsInsertedAfterTheConfiguredParagraph(): void
    {
        $client = self::createClient();
        $this->seed();
        $service = self::getContainer()->get(AdvertisementService::class);
        $service->save((new Advertisement())->setName('Inline')->setPlacement(AdvertisementPlacement::InArticle)
            ->setType(AdvertisementType::Html)->setContent('<p data-ad-marker="inline">Inline ad</p>')
            ->setAfterParagraph(2)->setPriority(10)->setIsActive(true));

        $crawler = $client->request('GET', '/blog/the-quiet-power-of-making-things-with-intention');
        self::assertResponseIsSuccessful();
        $html = $crawler->filter('[data-article-content]')->html();
        $position = strpos($html, 'data-ad-placement="in_article"');
        self::assertNotFalse($position);
        self::assertSame(2, substr_count(substr($html, 0, $position), '</p>'));
    }

    public function testSponsoredDisclosureAndAffiliateLinkAttributes(): void
    {
        $client = self::createClient();
        $this->seed();
        $article = self::getContainer()->get(ArticleRepository::class)->findOneBy(['slug' => 'the-quiet-power-of-making-things-with-intention']);
        $admin = self::getContainer()->get(UserRepository::class)->findOneByEmail('writer1@example.test');
        self::assertNotNull($article);
        self::assertNotNull($admin);
        $article->setIsSponsored(true)->setAffiliateLinks(true)
            ->setContent('<p>'.str_repeat('A sponsored story with useful context. ', 5).'<a href="https://partner.example/tool">Partner tool</a>.</p>');
        self::getContainer()->get(ArticleService::class)->update($article, $admin, null, false);
        self::getContainer()->get(TagAwareCacheInterface::class)->invalidateTags(['articles']);

        $crawler = $client->request('GET', '/blog/'.$article->getSlug());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'This story was produced in partnership');
        $link = $crawler->filter('[data-article-content] a[href="https://partner.example/tool"]');
        self::assertCount(1, $link);
        self::assertStringContainsString('sponsored', (string) $link->attr('rel'));
        self::assertStringContainsString('nofollow', (string) $link->attr('rel'));
    }

    public function testPremiumArticlesAreHiddenUntilAPolicyAllowsThem(): void
    {
        $client = self::createClient();
        $this->seed();
        $article = self::getContainer()->get(ArticleRepository::class)->findOneBy(['slug' => 'the-quiet-power-of-making-things-with-intention']);
        self::assertNotNull($article);
        $article->setIsPremium(true);
        self::getContainer()->get(EntityManagerInterface::class)->flush();
        self::getContainer()->get(TagAwareCacheInterface::class)->invalidateTags(['articles']);

        $client->request('GET', '/blog/'.$article->getSlug());
        self::assertResponseStatusCodeSame(404);
        $client->request('GET', '/');
        self::assertStringNotContainsString($article->getTitle(), (string) $client->getResponse()->getContent());
    }

    public function testAdminCanCreateToggleAndDeleteAnAdvertisement(): void
    {
        $client = self::createClient();
        $this->seed();
        $admin = self::getContainer()->get(UserRepository::class)->findOneByEmail('writer1@example.test');
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $crawler = $client->request('GET', '/admin/advertisements/new');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Save advertisement')->form();
        $form['advertisement_form[name]'] = 'Newsletter sponsor';
        $form['advertisement_form[type]'] = 'html';
        $form['advertisement_form[placement]'] = 'sidebar';
        $form['advertisement_form[content]'] = '<p data-admin-ad>Sidebar sponsor</p>';
        $form['advertisement_form[priority]'] = '20';
        $form['advertisement_form[isActive]'] = '1';
        $client->submit($form);
        self::assertResponseStatusCodeSame(302);

        $ad = self::getContainer()->get(AdvertisementRepository::class)->findOneBy(['name' => 'Newsletter sponsor']);
        self::assertNotNull($ad);
        self::assertTrue($ad->isActive());

        $crawler = $client->request('GET', '/admin/advertisements');
        self::assertResponseIsSuccessful();
        $token = (string) $crawler->filter('form[action$="/'.$ad->getId().'/toggle"] input[name="_token"]')->attr('value');
        $client->request('POST', '/admin/advertisements/'.$ad->getId().'/toggle', ['_token' => $token]);
        self::assertResponseStatusCodeSame(302);
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertFalse(self::getContainer()->get(AdvertisementRepository::class)->find($ad->getId())?->isActive());

        $crawler = $client->request('GET', '/admin/advertisements');
        $token = (string) $crawler->filter('form[action$="/'.$ad->getId().'/delete"] input[name="_token"]')->attr('value');
        $client->request('POST', '/admin/advertisements/'.$ad->getId().'/delete', ['_token' => $token]);
        self::assertResponseStatusCodeSame(302);
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertNull(self::getContainer()->get(AdvertisementRepository::class)->find($ad->getId()));
    }
}
