<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Setting;
use App\Repository\MenuItemRepository;
use App\Repository\MenuRepository;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Service\Settings\DatabaseSettingsService;
use Doctrine\ORM\EntityManagerInterface;

final class SettingsAndMenusTest extends FrontTestCase
{
    public function testSettingsPersistEncryptAndChangeThePublicSite(): void
    {
        $client = self::createClient();
        $this->seed();
        $admin = self::getContainer()->get(UserRepository::class)->findOneByEmail('writer1@example.test');
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $crawler = $client->request('GET', '/admin/settings');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Save general settings')->form();
        $form['settings_form[general_name]'] = 'The Slow Journal';
        $form['settings_form[general_tagline]'] = 'Stories worth keeping.';
        $client->submit($form);
        self::assertResponseStatusCodeSame(302);
        self::assertSame('The Slow Journal', self::getContainer()->get(DatabaseSettingsService::class)->getString('general.name'));

        $crawler = $client->request('GET', '/admin/appearance');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Save appearance settings')->form();
        $form['settings_form[appearance_primary_color]'] = '#ff0000';
        $form['settings_form[appearance_footer_text]'] = 'A footer set at runtime.';
        $client->submit($form);
        self::assertResponseStatusCodeSame(302);

        $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('--color-primary:255 0 0', (string) $client->getResponse()->getContent());
        self::assertStringContainsString('A footer set at runtime.', (string) $client->getResponse()->getContent());
        self::assertStringContainsString('The Slow Journal', (string) $client->getResponse()->getContent());

        self::getContainer()->get(DatabaseSettingsService::class)->saveGroup('appearance', [
            'appearance_homepage_sections' => 'authors, hero',
            'appearance_sidebar_widgets' => ['about'],
        ]);
        self::getContainer()->get(DatabaseSettingsService::class)->saveGroup('code', [
            'custom_css' => '.runtime-check{color:red}',
            'custom_js' => 'window.__fieldnotesRuntimeCheck = true;',
        ]);
        self::assertSame(['authors', 'hero'], self::getContainer()->get(DatabaseSettingsService::class)->getArray('appearance.homepage_sections'));
        $client->request('GET', '/');
        $html = (string) $client->getResponse()->getContent();
        self::assertLessThan(strpos($html, 'Stay curious.'), strpos($html, 'Meet our storytellers'));
        self::assertStringContainsString('.runtime-check{color:red}', $html);
        self::assertStringContainsString('window.__fieldnotesRuntimeCheck', $html);
        $article = self::getContainer()->get(\App\Repository\ArticleRepository::class)->findOneBy(['slug' => 'the-quiet-power-of-making-things-with-intention']);
        self::assertNotNull($article);
        $client->request('GET', '/blog/'.$article->getSlug());
        self::assertSelectorTextContains('aside', 'About');
        self::assertSelectorNotExists('nav[data-toc]');
    }

    public function testSmtpPasswordIsEncryptedAndSensitiveGroupsAreForbiddenToAuthors(): void
    {
        $client = self::createClient();
        $this->seed();
        $admin = self::getContainer()->get(UserRepository::class)->findOneByEmail('writer1@example.test');
        self::assertNotNull($admin);
        $client->loginUser($admin);
        $crawler = $client->request('GET', '/admin/settings/smtp');
        $form = $crawler->selectButton('Save email / smtp settings')->form();
        $form['settings_form[mail_transport]'] = 'smtp';
        $form['settings_form[mail_host]'] = 'smtp.example.test';
        $form['settings_form[mail_port]'] = '587';
        $form['settings_form[mail_username]'] = 'mailer';
        $form['settings_form[mail_password]'] = 'super-secret-smtp-password';
        $form['settings_form[mail_from_address]'] = 'hello@example.test';
        $client->submit($form);
        self::assertResponseStatusCodeSame(302);

        $setting = self::getContainer()->get(SettingRepository::class)->findOneByKey('mail.password');
        self::assertInstanceOf(Setting::class, $setting);
        $stored = (string) $setting->getValue()['value'];
        self::assertNotSame('super-secret-smtp-password', $stored);
        self::assertStringNotContainsString('super-secret-smtp-password', $stored);
        self::assertSame('super-secret-smtp-password', self::getContainer()->get(DatabaseSettingsService::class)->getString('mail.password'));

        $client->request('GET', '/admin/settings/smtp');
        self::assertSelectorExists('#settings-mail_password[type="password"]');
        self::assertSame('', $client->getCrawler()->filter('#settings-mail_password')->attr('value') ?? '');

        $author = self::getContainer()->get(UserRepository::class)->findOneByEmail('writer2@example.test');
        self::assertNotNull($author);
        $client->loginUser($author);
        foreach (['/admin/settings/smtp', '/admin/settings/code'] as $path) {
            $client->request('GET', $path);
            self::assertResponseStatusCodeSame(403, $path);
        }
    }

    public function testMaintenanceModeBlocksVisitorsButAllowsAdmins(): void
    {
        $client = self::createClient();
        $this->seed();
        self::getContainer()->get(DatabaseSettingsService::class)->saveGroup('general', [
            'maintenance_enabled' => true,
            'maintenance_message' => 'Back after the next edition.',
        ]);

        $client->request('GET', '/');
        self::assertResponseStatusCodeSame(503);
        self::assertSelectorTextContains('main', 'Back after the next edition.');

        $admin = self::getContainer()->get(UserRepository::class)->findOneByEmail('writer1@example.test');
        self::assertNotNull($admin);
        $client->loginUser($admin);
        $client->request('GET', '/');
        self::assertResponseIsSuccessful();
    }

    public function testMenuBuilderPersistsNestedItemsAndReordersThem(): void
    {
        $client = self::createClient();
        $this->seed();
        $admin = self::getContainer()->get(UserRepository::class)->findOneByEmail('writer1@example.test');
        self::assertNotNull($admin);
        $client->loginUser($admin);
        $menu = self::getContainer()->get(MenuRepository::class)->findOneBy([]);
        self::assertNotNull($menu);
        $crawler = $client->request('GET', '/admin/menus/'.$menu->getId().'/edit');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount($menu->getItems()->count(), '[data-menu-item]');

        $token = (string) $crawler->filter('[data-menu-sortable]')->attr('data-csrf-token');
        $items = self::getContainer()->get(MenuItemRepository::class)->findBy(['menu' => $menu], ['position' => 'ASC']);
        $payload = ['_token' => $token, 'items' => []];
        foreach (array_reverse($items) as $index => $item) {
            $payload['items'][] = ['id' => $item->getId(), 'parentId' => $item->getParent()?->getId(), 'position' => $index];
        }
        $client->request('POST', '/admin/menus/'.$menu->getId().'/reorder', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));
        self::assertResponseIsSuccessful();
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $reordered = self::getContainer()->get(MenuItemRepository::class)->findBy(['menu' => $menu], ['position' => 'ASC']);
        self::assertSame($payload['items'][0]['id'], $reordered[0]->getId());

        $crawler = $client->request('GET', '/admin/menus/'.$menu->getId().'/edit');
        $form = $crawler->selectButton('Add item')->form();
        $form['menu_item_form[label]'] = 'Reading list';
        $form['menu_item_form[type]'] = 'custom';
        $form['menu_item_form[target]'] = '/blog?sort=popular';
        $form['menu_item_form[position]'] = '9';
        $client->submit($form);
        self::assertResponseStatusCodeSame(302);
        $added = self::getContainer()->get(MenuItemRepository::class)->findOneBy(['label' => 'Reading list']);
        self::assertNotNull($added);

        $client->request('GET', '/');
        self::assertStringContainsString('Reading list', (string) $client->getResponse()->getContent());

        $crawler = $client->request('GET', '/admin/menus/'.$menu->getId().'/edit');
        $token = (string) $crawler->filter('form[action$="/items/'.$added->getId().'/delete"] input[name="_token"]')->attr('value');
        $client->request('POST', '/admin/menus/items/'.$added->getId().'/delete', ['_token' => $token]);
        self::assertResponseStatusCodeSame(302);
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertNull(self::getContainer()->get(MenuItemRepository::class)->find($added->getId()));
    }
}
