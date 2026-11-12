<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Mock\MockDataProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\TerminableInterface;

final class AdminSystemTest extends AdminTestCase
{
    /** @return list<string> */
    private static function paths(): array
    {
        return [
            '/admin/users', '/admin/users/new',
            '/admin/roles', '/admin/roles/new', '/admin/permissions',

            '/admin/activity',
        ];
    }

    /** @return iterable<string, array{string}> */
    public static function routes(): iterable
    {
        foreach (self::paths() as $path) {
            foreach (['', '?state=loading', '?state=empty', '?state=error'] as $query) {
                yield $path.$query => [$path.$query];
            }
        }
    }

    #[DataProvider('routes')]
    public function testSystemRoutesAndStates(string $path): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', $path);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('main h1');
        self::assertSelectorNotExists('main form form');
        self::assertSelectorNotExists('main iframe, main script[src^="https:"]');
        $ids = $crawler->filter('[id]')->each(static fn (Crawler $node): string => $node->attr('id') ?? '');
        self::assertSame($ids, array_values(array_unique($ids)), 'Duplicate HTML IDs on '.$path);
    }

    public function testUserAndRoleScreensAreReal(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/users');
        self::assertSelectorExists('#users-table');
        self::assertSelectorCount(1, '#users-table [data-row]');
        $client->request('GET', '/admin/users/new');
        self::assertSelectorExists('#user-name[required]');
        self::assertSelectorExists('#user-email[type="email"][required]');
        self::assertSelectorExists('input[name="admin_user_form[temporaryPassword]"][type="password"]');
        $client->request('GET', '/admin/roles');
        self::assertSelectorExists('table[aria-label="Roles"]');
        $client->request('GET', '/admin/permissions');
        self::assertSelectorExists('table[aria-label="Role permission matrix"]');
    }

    public function testRealSettingsRoutes(): void
    {
        $client = self::createClient();
        foreach (['/admin/analytics', '/admin/settings', '/admin/appearance', '/admin/seo', '/admin/settings/smtp', '/admin/settings/code'] as $path) {
            $crawler = $client->request('GET', $path);
            self::assertResponseIsSuccessful($path);
            self::assertSelectorExists('main h1');
            self::assertSelectorNotExists('main form form');
            $ids = $crawler->filter('[id]')->each(static fn (Crawler $node): string => $node->attr('id') ?? '');
            self::assertSame($ids, array_values(array_unique($ids)), 'Duplicate HTML IDs on '.$path);
        }

        $client->request('GET', '/admin/appearance');
        self::assertSelectorExists('#settings-appearance_primary_color[type="color"]');
        self::assertSelectorExists('#settings-appearance_accent_color[type="color"]');
        self::assertSelectorExists('#settings-appearance_homepage_sections');
        self::assertSelectorExists('input[name="settings_form[appearance_sidebar_widgets][]"][value="about"]');
        $client->request('GET', '/admin/settings');
        self::assertSelectorExists('#settings-general_name[value="Fieldnotes"]');
        self::assertSelectorExists('#settings-maintenance_enabled');
        $client->request('GET', '/admin/seo');
        self::assertSelectorTextContains('#settings-seo_robots', 'Disallow: /admin/');
        $client->request('GET', '/admin/settings/smtp');
        self::assertSelectorExists('#settings-mail_password[type="password"]');
        self::assertSelectorExists('#settings-mail_port[min="1"]');
        self::assertSelectorExists('form[action="/admin/settings/smtp/test"]');
        $client->request('GET', '/admin/settings/code');
        self::assertSelectorExists('#settings-custom_css');
        self::assertSelectorExists('#settings-custom_js');
    }

    public function testProfileRedirectsToTheRealProfileAndActivityIsReadOnly(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/profile');
        self::assertResponseRedirects('/profile');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('main h1');
        $client->request('GET', '/admin/activity');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('table[aria-label="Activity log"]');
        self::assertSelectorNotExists('[data-row-remove], [data-bulk-remove]');
    }

    public function testSystemLinksReadOnlyMethodsAndMissingIds(): void
    {
        $client = self::createClient(['debug' => false]);
        $links = [];
        foreach (self::paths() as $path) {
            $crawler = $client->request('GET', $path);
            self::assertResponseIsSuccessful();
            $links = array_merge($links, $crawler->filter('a[href^="/"]')->each(static fn (Crawler $node): string => explode('#', $node->attr('href') ?? '')[0]));
            if (str_ends_with($path, '/new') || str_ends_with($path, '/permissions')) {
                continue;
            }
            $client->request('POST', $path);
            self::assertResponseStatusCodeSame(405);
        }
        foreach (array_unique($links) as $path) {
            if (preg_match('#^/(admin/(articles|categories|tags)/\d+|authors/|categories/|tags/|blog/|media/cache/|uploads/)#', $path)) {
                continue;
            }
            $client->request('GET', $path);
            self::assertResponseIsSuccessful('Broken system link: '.$path.' '.substr(strip_tags((string) $client->getResponse()->getContent()), 0, 500));
        }
        foreach (['users', 'roles'] as $module) {
            $client->request('GET', '/admin/'.$module.'/999/edit');
            self::assertResponseStatusCodeSame(404);
        }
    }

    public function testSystemRoutesRequireAuthenticationAndProductionRedirects(): void
    {
        self::ensureKernelShutdown();
        $kernel = self::bootKernel();
        self::assertInstanceOf(TerminableInterface::class, $kernel);
        $request = Request::create('/admin/profile');
        $response = $kernel->handle($request);
        self::assertSame(302, $response->getStatusCode());
        self::assertStringContainsString('/login', (string) $response->headers->get('Location'));
        $kernel->terminate($request, $response);
        self::ensureKernelShutdown();
        $kernel = self::bootKernel(['environment' => 'prod', 'debug' => false]);
        self::assertInstanceOf(TerminableInterface::class, $kernel);
        foreach (self::paths() as $path) {
            $request = Request::create($path);
            $response = $kernel->handle($request);
            self::assertSame(302, $response->getStatusCode(), $path);
            self::assertStringContainsString('/login', (string) $response->headers->get('Location'));
            $kernel->terminate($request, $response);
        }
    }

    public function testPermissionAndAnalyticsFixtures(): void
    {
        $data = new MockDataProvider();
        $codes = array_column($data->permissions(), 'code');
        self::assertSame($codes, array_values(array_unique($codes)));
        foreach ($data->roles() as $role) {
            self::assertSame([], array_values(array_diff($role['permissions'], $codes)));
        }
        self::assertCount(30, $data->analyticsPreview()['daily']);
        self::assertSame('2026-10-13', $data->analyticsPreview()['daily'][0]['date']);
        self::assertSame('2026-11-11', $data->analyticsPreview()['daily'][29]['date']);
        self::assertArrayNotHasKey('password', $data->settingsPreview()['smtp']);
        self::assertArrayNotHasKey('totpSecret', $data->users()[0]);
    }
}
