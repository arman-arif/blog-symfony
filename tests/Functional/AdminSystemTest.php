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
            '/admin/users', '/admin/users/new', '/admin/users/1/edit', '/admin/users/6/edit',
            '/admin/roles', '/admin/roles/new', '/admin/roles/1/edit', '/admin/permissions',
            '/admin/newsletter', '/admin/newsletter/campaigns', '/admin/newsletter/history',
            '/admin/newsletter/campaigns/new', '/admin/newsletter/campaigns/2/edit',
            '/admin/analytics', '/admin/appearance', '/admin/seo', '/admin/settings',
            '/admin/settings/smtp', '/admin/activity', '/admin/profile',
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
        foreach ($crawler->filter('form:not([role="search"]):not([action="/logout"])') as $form) {
            self::assertCount(1, (new Crawler($form))->filter('fieldset[data-preview-fields][disabled]'), 'Unsafe preview form on '.$path);
        }
    }

    public function testUsersAndRoleAssignments(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/users');
        self::assertSelectorCount(6, '#users-table [data-row]');
        self::assertSelectorExists('[data-id="6"][data-status="inactive"]');
        self::assertSelectorCount(6, '[data-toggle-local-status][disabled]');
        $client->request('GET', '/admin/users/1/edit');
        self::assertSelectorExists('#user-role-1[checked][name="roles[]"]');
        self::assertSelectorExists('#user-email[type="email"][required]');
        $client->request('GET', '/admin/users/new');
        self::assertSelectorExists('#user-name[value=""][required]');
        self::assertSelectorNotExists('[name="password"]');
        $client->request('GET', '/admin/roles/1/edit');
        self::assertSelectorCount(count((new MockDataProvider())->permissions()), '[name="permissions[]"][checked]');
        self::assertSelectorExists('#role-permission-article-edit-own');
    }

    public function testPermissionMatrixHasEveryRoleAndCapability(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/permissions');
        $data = new MockDataProvider();
        self::assertSelectorCount(count($data->permissions()) * 5, '[data-permission-role]');
        self::assertSelectorCount(5, '[data-select-permission-role]');
        self::assertSelectorExists('[data-permission-role="1"][value="settings.smtp.manage"][checked]');
        self::assertSelectorNotExists('[data-permission-role="2"][value="settings.system.manage"][checked]');
        self::assertSelectorExists('[data-permission-summary][role="status"]');
    }

    public function testNewsletterContractsAndScheduling(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/newsletter');
        self::assertSelectorCount(18, '#subscribers-table [data-row]');
        self::assertSelectorExists('#subscribers-table-status option[value="inactive"]');
        self::assertSelectorExists('[data-export-preview][disabled]');
        self::assertSelectorExists('#export-preview-dialog [data-export-content]');
        $client->request('GET', '/admin/newsletter/campaigns');
        self::assertSelectorCount(3, '#campaigns-table [data-row]');
        $client->request('GET', '/admin/newsletter/history');
        self::assertSelectorCount(1, '#campaigns-table [data-row][data-status="sent"]');
        $client->request('GET', '/admin/newsletter/campaigns/2/edit');
        self::assertSelectorExists('textarea[name="html"][readonly]');
        self::assertSelectorNotExists('textarea[name="content"]');
        self::assertSelectorExists('#campaign-schedule[value="2026-12-01T09:00"]');
        self::assertSelectorExists('#campaign-status option[value="scheduled"][selected]');
        self::assertSelectorExists('#campaign-test-dialog form fieldset[disabled]');
    }

    public function testAnalyticsAlternativesAndDateInputs(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/analytics');
        self::assertSelectorCount(2, 'canvas[role="img"][aria-describedby]');
        self::assertSelectorCount(30, '#analytics-views-chart-table tbody tr');
        self::assertSelectorExists('#analytics-from[type="date"][required]');
        self::assertSelectorExists('#analytics-to[type="date"][required]');
        self::assertSelectorExists('[data-analytics-stat="views"] [data-stat-value]');
        self::assertSelectorTextContains('main', 'Popular tags');
        self::assertSelectorTextContains('main', 'Author performance');
        self::assertSelectorTextContains('main', 'no tracking');
    }

    public function testAppearanceAndSettingsContracts(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/appearance');
        self::assertSelectorCount(8, '[data-appearance-section]');
        self::assertSelectorCount(16, '[data-section-move]');
        self::assertSelectorExists('#appearance-primary[type="color"]');
        self::assertSelectorExists('[data-appearance-sample]');
        self::assertSelectorCount(2, '[data-safe-url][type="url"]');
        $client->request('GET', '/admin/settings');
        self::assertSelectorExists('[name="logo"][value="1"]');
        self::assertSelectorExists('[name="favicon"][value=""]');
        foreach (['timezone', 'dateFormat', 'timeFormat', 'language', 'registration', 'comments', 'doubleOptIn', 'maintenance', 'uploadLimit', 'cacheTtl', 'customCss', 'customJavaScript'] as $name) {
            self::assertSelectorExists('[name="'.$name.'"]');
        }
        self::assertSelectorTextContains('main', 'never executed or applied');
        $client->request('GET', '/admin/seo');
        self::assertSelectorExists('#site-seo-title[maxlength="70"]');
        self::assertSelectorExists('#seo-canonical[type="url"][data-safe-url]');
        self::assertSelectorTextContains('#seo-robots', 'Disallow: /admin/');
        self::assertSelectorExists('#seo-ga[pattern="G-[A-Z0-9]+"]');
        self::assertSelectorExists('[data-redirect-rows] tr');
        self::assertSelectorExists('#redirect-form [data-local-path]');
    }

    public function testSensitiveFormsAndReadOnlyActivity(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/settings/smtp');
        self::assertSelectorExists('#smtp-password[type="password"][value=""][data-sensitive-preview]');
        self::assertSelectorExists('#smtp-port[min="1"][max="65535"]');
        self::assertSelectorExists('#smtp-test-dialog form fieldset[disabled]');
        $client->request('GET', '/admin/profile');
        self::assertSelectorCount(4, '[data-sensitive-preview]');
        self::assertSelectorExists('#password-confirm[data-password-confirm="password-new"]');
        self::assertSelectorExists('#two-factor-code[pattern="[0-9]{6}"]');
        self::assertSelectorExists('[role="img"][aria-label="QR code placeholder, not scannable"]');
        self::assertSelectorTextContains('main', 'No password is sent or stored');
        $client->request('GET', '/admin/activity');
        self::assertSelectorCount(5, '[data-activity-detail][disabled]');
        self::assertSelectorNotExists('[data-row-remove], [data-bulk-remove]');
        self::assertSelectorExists('#activity-detail-dialog [data-activity-context]');
    }

    public function testSystemLinksReadOnlyMethodsAndMissingIds(): void
    {
        $client = self::createClient(['debug' => false]);
        $links = [];
        foreach (self::paths() as $path) {
            $crawler = $client->request('GET', $path);
            self::assertResponseIsSuccessful();
            $links = array_merge($links, $crawler->filter('a[href^="/"]')->each(static fn (Crawler $node): string => explode('#', $node->attr('href') ?? '')[0]));
            $client->request('POST', $path);
            self::assertResponseStatusCodeSame(405);
        }
        foreach (array_unique($links) as $path) {
            if (preg_match('#^/admin/(articles|categories|tags)/\d+#', $path)) {
                continue;
            }
            $client->request('GET', $path);
            self::assertResponseIsSuccessful('Broken system link: '.$path.' '.substr(strip_tags((string) $client->getResponse()->getContent()), 0, 500));
        }
        foreach (['users', 'roles', 'newsletter/campaigns'] as $module) {
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
