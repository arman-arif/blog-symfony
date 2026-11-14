<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\ArticleView;
use App\Entity\MenuItem;
use App\Message\AggregateAnalyticsMessage;
use App\MessageHandler\AggregateAnalyticsHandler;
use App\Repository\ArticleRepository;
use App\Repository\MenuItemRepository;
use App\Repository\MenuRepository;
use App\Repository\UserRepository;
use App\Service\Menu\MenuService;
use App\Service\Settings\DatabaseSettingsService;
use App\Service\Settings\DefaultSettingsService;
use App\Service\Settings\SettingsCatalog;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Validator\Exception\ValidationFailedException;

final class SettingsMenuValidationRegressionTest extends FrontTestCase
{
    public function testAllRuntimeDefaultsHaveValidatedControlsAndSystemIsRestricted(): void
    {
        $client = self::createClient();
        $this->seed();
        self::assertSame([], array_diff(array_keys(DefaultSettingsService::DEFAULTS), array_keys(SettingsCatalog::FIELDS)));
        $users = self::getContainer()->get(UserRepository::class);
        $client->loginUser($users->findOneByEmail('writer1@example.test'));
        foreach (['general' => '/admin/settings', 'system' => '/admin/settings/system'] as $group => $path) {
            $crawler = $client->request('GET', $path);
            self::assertResponseIsSuccessful();
            foreach (SettingsCatalog::fieldsForGroup($group) as $key => $field) {
                self::assertCount(1, $crawler->filter('[name="settings_form['.SettingsCatalog::formName($key).']"]'));
            }
        }
        $form = $crawler->selectButton('Save system settings')->form();
        $form['settings_form[analytics_retention_days]'] = '45';
        $client->submit($form);
        self::assertResponseRedirects('/admin/settings/system');
        self::assertSame(45, self::getContainer()->get(DatabaseSettingsService::class)->getInt('analytics.retention_days'));

        foreach (['ROLE_ADMIN', 'ROLE_EDITOR', 'ROLE_AUTHOR'] as $role) {
            $user = array_find($users->findAll(), static fn ($user): bool => $user->hasRoleCode($role));
            self::assertNotNull($user);
            $client->loginUser($user);
            $client->request('GET', '/admin/settings/system');
            self::assertResponseStatusCodeSame(403);
            $client->request('POST', '/admin/settings/system', ['settings_form' => ['analytics_retention_days' => 7]]);
            self::assertResponseStatusCodeSame(403);
        }
    }

    /** @return iterable<string, array{string, string, mixed}> */
    public static function invalidSettings(): iterable
    {
        yield 'port' => ['smtp', 'mail_port', 70000];
        yield 'boolean string' => ['general', 'comments_anonymous', 'false'];
        yield 'timezone' => ['general', 'general_timezone', 'Not/AZone'];
        yield 'locale' => ['general', 'general_locale', 'bogus_locale'];
        yield 'depth' => ['general', 'comments_max_depth', -1];
        yield 'retention' => ['system', 'analytics_retention_days', 6];
        yield 'upload' => ['system', 'media_max_upload_bytes', 12582913];
        yield 'unknown section' => ['appearance', 'appearance_homepage_sections', ['hero', 'bogus']];
        yield 'duplicate section' => ['appearance', 'appearance_homepage_sections', ['hero', 'hero']];
        yield 'social protocol' => ['appearance', 'appearance_social_links', [['label' => 'Bad', 'url' => 'javascript:alert(1)']]];
        yield 'social shape' => ['appearance', 'appearance_social_links', ['not an object']];
        yield 'social missing label' => ['appearance', 'appearance_social_links', [['url' => 'https://example.test']]];
    }

    #[DataProvider('invalidSettings')]
    public function testInvalidServiceWritesDoNotMutateManagedSettings(string $group, string $key, mixed $invalid): void
    {
        self::createClient();
        $this->seed();
        $settings = self::getContainer()->get(DatabaseSettingsService::class);
        $firstKey = array_key_first(SettingsCatalog::fieldsForGroup($group));
        $firstName = SettingsCatalog::formName($firstKey);
        $before = $settings->get($firstKey);
        $changed = is_bool($before) ? !$before : 'Rejected change';
        if ('system' === $group) {
            $changed = !$before;
        }
        try {
            $settings->saveGroup($group, [$firstName => $changed, $key => $invalid]);
            self::fail('Invalid data must fail before managed mutations.');
        } catch (ValidationFailedException) {
            self::assertTrue(self::getContainer()->get(EntityManagerInterface::class)->isOpen());
        }
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->flush();
        $em->clear();
        self::getContainer()->get('cache.content')->invalidateTags(['settings']);
        self::assertSame($before, $settings->get($firstKey));
    }

    public function testHttpValidationAndOptionalBlankSettings(): void
    {
        $client = self::createClient();
        $this->seed();
        $client->loginUser(self::getContainer()->get(UserRepository::class)->findOneByEmail('writer1@example.test'));
        $crawler = $client->request('GET', '/admin/settings');
        $form = $crawler->selectButton('Save general settings')->form();
        $form['settings_form[general_name]'] = 'Rejected name';
        $form['settings_form[comments_max_depth]'] = '-1';
        $client->submit($form);
        self::assertResponseStatusCodeSame(422);
        self::getContainer()->get(EntityManagerInterface::class)->flush();
        self::assertSame('Fieldnotes', self::getContainer()->get(DatabaseSettingsService::class)->getString('general.name'));
        $crawler = $client->request('GET', '/admin/settings/smtp');
        $form = $crawler->selectButton('Save email / smtp settings')->form();
        $form['settings_form[mail_encryption]'] = '';
        $client->submit($form);
        self::assertResponseStatusCodeSame(302);
        self::assertSame('', self::getContainer()->get(DatabaseSettingsService::class)->getString('mail.encryption'));

        $crawler = $client->request('GET', '/admin/appearance');
        $form = $crawler->selectButton('Save appearance settings')->form();
        $form['settings_form[appearance_social_links]'] = '{invalid json';
        $client->submit($form);
        self::assertResponseStatusCodeSame(422);
        $crawler = $client->request('GET', '/admin/appearance');
        $form = $crawler->selectButton('Save appearance settings')->form();
        $form['settings_form[appearance_social_links]'] = '[{"label":"Mastodon","url":"https://example.social/"}]';
        $client->submit($form);
        self::assertResponseStatusCodeSame(302);
        $client->request('GET', '/');
        self::assertSelectorTextContains('footer a[href="https://example.social/"]', 'Mastodon');
    }

    public function testUnknownGroupsAndFieldsAreRejected(): void
    {
        self::createClient();
        $this->seed();
        $settings = self::getContainer()->get(DatabaseSettingsService::class);
        try {
            $settings->saveGroup('bogus', []);
            self::fail('Unknown group accepted.');
        } catch (\OutOfBoundsException $exception) {
            self::assertSame('Unknown settings group.', $exception->getMessage());
        }
        $this->expectException(\InvalidArgumentException::class);
        $settings->saveGroup('general', ['media_max_upload_bytes' => 2000]);
    }

    public function testSavedRetentionDrivesBothScheduledAndCommandPruning(): void
    {
        self::createClient();
        $this->seed();
        self::getContainer()->get(DatabaseSettingsService::class)->saveGroup('system', ['analytics_retention_days' => 7]);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $article = self::getContainer()->get(ArticleRepository::class)->findOneBy([]);
        $view = static fn (string $hash): ArticleView => (new ArticleView())->setArticle($article)->setVisitorHash($hash)->setViewedAt(new \DateTimeImmutable('-10 days'));
        $em->persist($view('scheduler'));
        $em->flush();
        self::getContainer()->get(AggregateAnalyticsHandler::class)(new AggregateAnalyticsMessage());
        self::assertSame(0, (int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM article_view'));
        $em->persist($view('command'));
        $em->flush();
        $tester = new CommandTester((new Application(self::$kernel))->find('app:analytics:rollup'));
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();
        self::assertSame(0, (int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM article_view'));
    }

    public function testMenuEditCyclesAndCrossMenuParentsAreRejectedWithoutPendingWrites(): void
    {
        $client = self::createClient();
        $this->seed();
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $client->loginUser(self::getContainer()->get(UserRepository::class)->findOneByEmail('writer1@example.test'));
        $menus = self::getContainer()->get(MenuRepository::class)->findAll();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $root = $menus[0]->getItems()->first();
        $child = (new MenuItem())->setMenu($menus[0])->setParent($root)->setLabel('Child')->setTarget('/blog');
        $em->persist($child);
        $em->flush();
        $id = $root->getId();
        $label = $root->getLabel();
        $em->clear();
        $crawler = $client->request('GET', '/admin/menus/items/'.$id.'/edit');
        $form = $crawler->selectButton('Save item')->form();
        $form['menu_item_form[parent]'] = (string) $child->getId();
        $form['menu_item_form[label]'] = 'Rejected label';
        $client->submit($form);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'cannot form cycles');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->flush();
        $em->clear();
        $root = self::getContainer()->get(MenuItemRepository::class)->find($id);
        self::assertSame($label, $root->getLabel());
        self::assertNull($root->getParent());
        $details = clone $root;
        $details->setParent(self::getContainer()->get(MenuItemRepository::class)->findOneBy(['menu' => $menus[1]->getId()]));
        $this->expectException(\DomainException::class);
        self::getContainer()->get(MenuService::class)->saveItem($root, $details);
    }

    public function testReorderValidatesCompleteGraphAndTypedPayloadBeforeMutation(): void
    {
        $client = self::createClient();
        $this->seed();
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $client->loginUser(self::getContainer()->get(UserRepository::class)->findOneByEmail('writer1@example.test'));
        $menus = self::getContainer()->get(MenuRepository::class)->findAll();
        $menu = $menus[0];
        $items = $menu->getItems()->toArray();
        [$root, $child] = $items;
        $child->setParent($root);
        self::getContainer()->get(EntityManagerInterface::class)->flush();
        $crawler = $client->request('GET', '/admin/menus/'.$menu->getId().'/edit');
        $token = $crawler->filter('[data-menu-sortable]')->attr('data-csrf-token');
        $invalid = [
            [['id' => $root->getId(), 'position' => 99, 'parentId' => $child->getId()]],
            [['id' => $root->getId(), 'position' => 99, 'parentId' => $root->getId()]],
            [['id' => $root->getId(), 'position' => 99, 'parentId' => $menus[1]->getItems()->first()->getId()]],
            [['id' => $root->getId(), 'position' => 99], ['id' => $root->getId(), 'position' => 100]],
            [['id' => $root->getId(), 'position' => -1]],
            [['id' => 'not-an-id', 'position' => 0]],
        ];
        $position = $root->getPosition();
        foreach ($invalid as $rows) {
            $client->jsonRequest('POST', '/admin/menus/'.$menu->getId().'/reorder', ['_token' => $token, 'items' => $rows]);
            self::assertResponseStatusCodeSame(422);
            $em = self::getContainer()->get(EntityManagerInterface::class);
            $em->flush();
            $em->clear();
            $unchanged = self::getContainer()->get(MenuItemRepository::class)->find($root->getId());
            self::assertNull($unchanged->getParent());
            self::assertSame($position, $unchanged->getPosition());
        }
        $client->jsonRequest('POST', '/admin/menus/'.$menu->getId().'/reorder', ['_token' => 'wrong', 'items' => [['id' => $root->getId(), 'position' => 1]]]);
        self::assertResponseStatusCodeSame(403);
        $client->jsonRequest('POST', '/admin/menus/'.$menu->getId().'/reorder', ['_token' => $token, 'items' => [['id' => $child->getId(), 'position' => 9, 'parentId' => null]]]);
        self::assertResponseIsSuccessful();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $child = self::getContainer()->get(MenuItemRepository::class)->find($child->getId());
        self::assertNull($child->getParent());
        self::assertSame(9, $child->getPosition());
    }
}
