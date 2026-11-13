<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Category;
use App\Entity\Page;
use App\Entity\Tag;
use App\Enum\PageStatus;
use App\Repository\PageRepository;
use App\Service\Article\TaxonomyService;
use App\Service\Page\PageService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;

final class SitemapPageRegressionTest extends AdminTestCase
{
    public function testCachedSitemapTracksPublicationSlugNoindexUnpublishAndDelete(): void
    {
        $client = $this->start();
        $this->assertSitemap($client, 'sitemap-pages.xml', '/pages/cache-aware-page', false);
        $form = $client->request('GET', '/admin/pages/new')->selectButton('Save page')->form();
        $form['page_form[title]'] = 'Cache aware page';
        $form['page_form[slug]'] = 'cache-aware-page';
        $form['page_form[content]'] = '<p>Public page body.</p><script>alert(1)</script>';
        $form['page_form[status]'] = 'published';
        $form['page_form[seo][title]'] = 'Search aware page';
        $form['page_form[seo][description]'] = 'A custom page description.';
        $client->submit($form);
        self::assertResponseRedirects('/admin/pages');
        $page = self::getContainer()->get(PageRepository::class)->findOneBy(['slug' => 'cache-aware-page']);
        self::assertNotNull($page);
        $id = $page->getId();
        self::assertStringNotContainsString('<script', $page->getContent());
        $this->assertSitemap($client, 'sitemap-pages.xml', '/pages/cache-aware-page', true);
        $client->request('GET', '/pages/cache-aware-page');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('title', 'Search aware page');
        self::assertSelectorExists('meta[name="description"][content="A custom page description."]');

        $form = $client->request('GET', '/admin/pages/'.$id.'/edit')->selectButton('Save page')->form();
        $form['page_form[slug]'] = 'renamed-cache-page';
        $client->submit($form);
        self::assertResponseRedirects('/admin/pages');
        $this->assertSitemap($client, 'sitemap-pages.xml', '/pages/cache-aware-page', false);
        $this->assertSitemap($client, 'sitemap-pages.xml', '/pages/renamed-cache-page', true);

        foreach ([['published', true, false], ['published', false, true], ['draft', false, false], ['published', false, true]] as [$status, $noindex, $listed]) {
            $form = $client->request('GET', '/admin/pages/'.$id.'/edit')->selectButton('Save page')->form();
            $form['page_form[status]'] = $status;
            $checkbox = $form['page_form[seo][noindex]'];
            self::assertInstanceOf(ChoiceFormField::class, $checkbox);
            $checkbox->setValue($noindex);
            $client->submit($form);
            self::assertResponseRedirects('/admin/pages');
            $this->assertSitemap($client, 'sitemap-pages.xml', '/pages/renamed-cache-page', $listed);
        }

        $crawler = $client->request('GET', '/admin/pages');
        $token = (string) $crawler->filter('form[action$="/'.$id.'/delete"] input[name="_token"]')->attr('value');
        $client->request('POST', '/admin/pages/'.$id.'/delete', ['_token' => $token]);
        self::assertResponseRedirects('/admin/pages');
        $this->assertSitemap($client, 'sitemap-pages.xml', '/pages/renamed-cache-page', false);
        $client->request('GET', '/pages/renamed-cache-page');
        self::assertResponseStatusCodeSame(404);
    }

    public function testRejectedPageFormDoesNotLeakFieldsOrSeoIntoALaterFlush(): void
    {
        $client = $this->start();
        $page = $this->page();
        $id = $page->getId();
        $form = $client->request('GET', '/admin/pages/'.$id.'/edit')->selectButton('Save page')->form();
        $form['page_form[title]'] = '';
        $form['page_form[slug]'] = 'rejected-page-url';
        $form['page_form[content]'] = '<p>Rejected body.</p>';
        $form['page_form[status]'] = 'draft';
        $form['page_form[seo][title]'] = 'Rejected SEO';
        $form['page_form[seo][noindex]'] = '1';
        $client->submit($form);
        self::assertResponseStatusCodeSame(422);
        self::assertInputValueSame('page_form[slug]', 'rejected-page-url');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->flush();
        $em->clear();
        $page = self::getContainer()->get(PageRepository::class)->find($id);
        self::assertNotNull($page);
        self::assertSame('Original page', $page->getTitle());
        self::assertSame('original-page', $page->getSlug());
        self::assertSame('<p>Original body.</p>', $page->getContent());
        self::assertSame(PageStatus::Published, $page->getStatus());
        self::assertSame('Original SEO', $page->getSeo()->getTitle());
        self::assertFalse($page->getSeo()->isNoindex());
    }

    /** @return iterable<string, array{string}> */
    public static function hiddenPages(): iterable
    {
        foreach (['draft', 'future', 'undated', 'deleted'] as $kind) {
            yield $kind => [$kind];
        }
    }

    #[DataProvider('hiddenPages')]
    public function testPublicPageAndSitemapSharePublicationEligibility(string $kind): void
    {
        $client = $this->start();
        $page = $this->page();
        match ($kind) {
            'draft' => $page->setStatus(PageStatus::Draft),
            'future' => $page->setPublishedAt(new \DateTimeImmutable('+1 day')),
            'undated' => $page->setPublishedAt(null),
            'deleted' => $page->setDeletedAt(new \DateTimeImmutable()),
            default => throw new \InvalidArgumentException($kind),
        };
        self::getContainer()->get(EntityManagerInterface::class)->flush();
        self::assertNull(self::getContainer()->get(PageRepository::class)->findPublishedBySlug('original-page'));
        self::assertCount(0, self::getContainer()->get(PageRepository::class)->findForSitemap());
        $this->assertSitemap($client, 'sitemap-pages.xml', '/pages/original-page', false);
        $client->request('GET', '/pages/original-page');
        self::assertResponseStatusCodeSame(404);
    }

    public function testPageSlugsPreserveManualValuesAndRespectTrashCollisions(): void
    {
        $this->start();
        self::assertNotNull(self::$adminUser);
        $service = self::getContainer()->get(PageService::class);
        $page = new Page();
        $details = (new Page())->setTitle('Manual page')->setSlug('kept-page')->setContent('<p>Body.</p>');
        $service->save($page, $details, self::$adminUser);
        self::assertSame('kept-page', $page->getSlug());
        $details->setTitle('Renamed page');
        $service->save($page, $details, self::$adminUser);
        self::assertSame('kept-page', $page->getSlug());
        $service->delete($page);
        $next = new Page();
        $service->save($next, $details, self::$adminUser);
        self::assertSame('kept-page-2', $next->getSlug());
        self::assertTrue(self::getContainer()->get(EntityManagerInterface::class)->getFilters()->isEnabled('softdeleteable'));
    }

    /** @return iterable<string, array{string}> */
    public static function taxonomies(): iterable
    {
        yield 'category' => ['category'];
        yield 'tag' => ['tag'];
    }

    #[DataProvider('taxonomies')]
    public function testTaxonomyMutationsInvalidateTheActualPublicCachePool(string $kind): void
    {
        $client = $this->start();
        $item = 'category' === $kind ? new Category() : new Tag();
        $plural = 'category' === $kind ? 'categories' : 'tags';
        $sitemap = 'sitemap-'.$plural.'.xml';
        $url = '/'.$plural.'/cache-taxonomy';
        $item->setName('Cache taxonomy')->setSlug('cache-taxonomy');
        $this->assertSitemap($client, $sitemap, $url, false);
        // HTTP reboots the kernel: reload services and persist into the current EntityManager.
        $service = self::getContainer()->get(TaxonomyService::class);
        $item instanceof Category ? $service->saveCategory($item) : $service->saveTag($item);
        $this->assertSitemap($client, $sitemap, $url, true);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $item = $em->find($item::class, $item->getId());
        self::assertNotNull($item);
        $item->getSeo()->setNoindex(true);
        $service = self::getContainer()->get(TaxonomyService::class);
        $item instanceof Category ? $service->saveCategory($item) : $service->saveTag($item);
        $this->assertSitemap($client, $sitemap, $url, false);
        $item = self::getContainer()->get(EntityManagerInterface::class)->find($item::class, $item->getId());
        self::assertNotNull($item);
        $item->getSeo()->setNoindex(false);
        $service = self::getContainer()->get(TaxonomyService::class);
        $item instanceof Category ? $service->saveCategory($item) : $service->saveTag($item);
        $this->assertSitemap($client, $sitemap, $url, true);
        $item = self::getContainer()->get(EntityManagerInterface::class)->find($item::class, $item->getId());
        self::assertNotNull($item);
        $service = self::getContainer()->get(TaxonomyService::class);
        $item instanceof Category ? $service->deleteCategory($item) : $service->deleteTag($item);
        $this->assertSitemap($client, $sitemap, $url, false);
    }

    private function start(): KernelBrowser
    {
        $client = self::createClient();
        self::getContainer()->get('cache.content')->clear();

        return $client;
    }

    private function page(): Page
    {
        $page = (new Page())->setTitle('Original page')->setSlug('original-page')->setContent('<p>Original body.</p>')
            ->setStatus(PageStatus::Published)->setPublishedAt(new \DateTimeImmutable('-1 day'));
        $page->getSeo()->setTitle('Original SEO');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($page);
        $em->flush();

        return $page;
    }

    private function assertSitemap(KernelBrowser $client, string $path, string $urlPath, bool $listed): void
    {
        $client->request('GET', '/'.$path);
        self::assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();
        if ($listed) {
            self::assertStringContainsString($urlPath, $content);
        } else {
            self::assertStringNotContainsString($urlPath, $content);
        }
    }
}
