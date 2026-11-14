<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Article;
use App\Entity\ArticleDailyStat;
use App\Entity\Role;
use App\Entity\User;
use App\Enum\ArticleStatus;
use App\Repository\ArticleRepository;
use App\Repository\RoleRepository;
use App\Repository\UserRepository;
use App\Security\UserChecker;
use App\Service\User\UserManagementService;
use App\Tests\Support\InMemorySettingsService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

final class AuthorizationRegressionTest extends FrontTestCase
{
    private const string SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

    protected function tearDown(): void
    {
        InMemorySettingsService::resetOverride();
        parent::tearDown();
    }

    /** @return iterable<string, array{string}> */
    public static function elevatedRoles(): iterable
    {
        yield 'super administrator' => ['ROLE_SUPER_ADMIN'];
        yield 'administrator' => ['ROLE_ADMIN'];
    }

    #[DataProvider('elevatedRoles')]
    public function testAdministratorCannotCreateOrPromoteAdministrativeAccounts(string $code): void
    {
        $client = self::createClient();
        $this->seed();
        $actor = $this->user('ROLE_ADMIN');
        $role = self::getContainer()->get(RoleRepository::class)->findOneByCode($code);
        self::assertNotNull($role);
        $client->loginUser($actor);
        $crawler = $client->request('GET', '/admin/users/new');
        self::assertSelectorNotExists('input[name="admin_user_form[assignedRoles][]"][value="'.$role->getId().'"]');
        $token = (string) $crawler->filter('input[name="admin_user_form[_token]"]')->attr('value');
        $client->request('POST', '/admin/users/new', ['admin_user_form' => [
            'displayName' => 'Escalation attempt', 'email' => 'escalation@example.test', 'jobTitle' => '', 'bio' => '',
            'temporaryPassword' => 'uncommon-long-password', 'assignedRoles' => [(string) $role->getId()], 'isActive' => '1', '_token' => $token,
        ]]);
        self::assertResponseStatusCodeSame(422);
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertNull(self::getContainer()->get(UserRepository::class)->findOneByEmail('escalation@example.test'));

        $target = $this->user('ROLE_AUTHOR');
        $id = $target->getId();
        $crawler = $client->request('GET', '/admin/users/'.$id.'/edit');
        $token = (string) $crawler->filter('input[name="admin_user_form[_token]"]')->attr('value');
        $client->request('POST', '/admin/users/'.$id.'/edit', ['admin_user_form' => [
            'displayName' => $target->getDisplayName(), 'email' => $target->getEmail(), 'jobTitle' => '', 'bio' => '',
            'temporaryPassword' => '', 'assignedRoles' => [(string) $role->getId()], 'isActive' => '1', '_token' => $token,
        ]]);
        self::assertResponseStatusCodeSame(422);
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertFalse(self::getContainer()->get(UserRepository::class)->find($id)->hasRoleCode($code));
    }

    public function testAdministratorCannotEditOtherAdministratorsOrAssignAHighPrivilegeCustomRole(): void
    {
        $client = self::createClient();
        $this->seed();
        $actor = $this->user('ROLE_ADMIN');
        $client->loginUser($actor);
        foreach ([$actor, $this->user('ROLE_SUPER_ADMIN')] as $target) {
            $client->request('GET', '/admin/users/'.$target->getId().'/edit');
            self::assertResponseStatusCodeSame(403);
        }
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $superRole = self::getContainer()->get(RoleRepository::class)->findOneByCode('ROLE_SUPER_ADMIN');
        $customRole = (new Role())->setName('Custom system operator')->setCode('ROLE_CUSTOM_SYSTEM');
        foreach ($superRole->getPermissions() as $permission) {
            $customRole->addPermission($permission);
        }
        $em->persist($customRole);
        $em->flush();
        $this->expectException(AccessDeniedException::class);
        self::getContainer()->get(UserManagementService::class)->save($this->user('ROLE_AUTHOR'), $actor, [$customRole], '', false);
    }

    public function testLastActiveSuperAdminCannotBeDeactivatedEvenWithAnInactiveSuperAdmin(): void
    {
        $client = self::createClient();
        $this->seed();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $last = $this->user('ROLE_SUPER_ADMIN');
        $superRole = self::getContainer()->get(RoleRepository::class)->findOneByCode('ROLE_SUPER_ADMIN');
        foreach (self::getContainer()->get(UserRepository::class)->findAll() as $user) {
            if ($user !== $last && $user->hasRoleCode('ROLE_SUPER_ADMIN')) {
                $user->setIsActive(false);
            }
        }
        $em->flush();
        self::assertSame(1, self::getContainer()->get(UserRepository::class)->countSuperAdmins());
        $id = $last->getId();
        $email = $last->getEmail();
        $client->loginUser($last);
        $crawler = $client->request('GET', '/admin/users/'.$id.'/edit');
        $token = (string) $crawler->filter('input[name="admin_user_form[_token]"]')->attr('value');
        $client->request('POST', '/admin/users/'.$id.'/edit', ['admin_user_form' => [
            'displayName' => 'Rejected name', 'email' => $email, 'jobTitle' => '', 'bio' => '',
            'temporaryPassword' => '', 'assignedRoles' => [(string) $superRole->getId()], '_token' => $token,
        ]]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'last Super Administrator');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->flush();
        $em->clear();
        $reloaded = self::getContainer()->get(UserRepository::class)->find($id);
        self::assertTrue($reloaded->isActive());
        self::assertNotSame('Rejected name', $reloaded->getDisplayName());
    }

    public function testInvalidUserFormCannotPersistDeactivationOnALaterFlush(): void
    {
        $client = self::createClient();
        $this->seed();
        $admin = $this->user('ROLE_SUPER_ADMIN');
        $id = $admin->getId();
        $client->loginUser($admin);
        $crawler = $client->request('GET', '/admin/users/'.$id.'/edit');
        $token = (string) $crawler->filter('input[name="admin_user_form[_token]"]')->attr('value');
        $client->request('POST', '/admin/users/'.$id.'/edit', ['admin_user_form' => [
            'displayName' => 'Rejected change', 'email' => 'invalid', 'jobTitle' => '', 'bio' => '',
            'temporaryPassword' => '', 'assignedRoles' => [], '_token' => $token,
        ]]);
        self::assertResponseStatusCodeSame(422);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->flush();
        $em->clear();
        $reloaded = self::getContainer()->get(UserRepository::class)->find($id);
        self::assertTrue($reloaded->isActive());
        self::assertTrue($reloaded->hasRoleCode('ROLE_SUPER_ADMIN'));
        self::assertNotSame('Rejected change', $reloaded->getDisplayName());
    }

    public function testContributorCannotMutatePublishedOrSubmittedArticles(): void
    {
        $client = self::createClient();
        $this->seed();
        $contributor = $this->user('ROLE_CONTRIBUTOR');
        $client->loginUser($contributor);
        foreach ([ArticleStatus::Published, ArticleStatus::PendingReview, ArticleStatus::Scheduled] as $status) {
            $article = $this->article($this->user('ROLE_CONTRIBUTOR'), $status, 'Restricted contributor '.$status->value);
            $id = $article->getId();
            foreach (['edit', 'preview', 'revisions'] as $action) {
                $client->request('GET', '/admin/articles/'.$id.'/'.$action);
                self::assertResponseStatusCodeSame(403);
            }
            foreach (['edit', 'delete', 'duplicate', 'autosave'] as $action) {
                $client->request('POST', '/admin/articles/'.$id.'/'.$action);
                self::assertResponseStatusCodeSame(403);
            }
            self::getContainer()->get(EntityManagerInterface::class)->clear();
            self::assertSame($status, self::getContainer()->get(ArticleRepository::class)->find($id)->getStatus());
        }
        $draft = $this->article($this->user('ROLE_CONTRIBUTOR'), ArticleStatus::Draft, 'Editable contributor draft');
        $client->request('GET', '/admin/articles/'.$draft->getId().'/edit');
        self::assertResponseIsSuccessful();
    }

    public function testAuthorsCannotReadOtherAuthorsDraftsThroughListsFiltersOrDashboard(): void
    {
        $client = self::createClient();
        $this->seed();
        $other = $this->user('ROLE_EDITOR');
        $this->article($other, ArticleStatus::Draft, 'Confidential editorial draft');
        $client->loginUser($this->user('ROLE_AUTHOR'));
        foreach (['/admin/articles?q=Confidential', '/admin/articles?author='.$other->getId(), '/admin'] as $path) {
            $client->request('GET', $path);
            self::assertResponseIsSuccessful();
            self::assertStringNotContainsString('Confidential editorial draft', (string) $client->getResponse()->getContent());
        }
        self::assertSelectorNotExists('a[href="/admin/activity"]');
        self::assertSelectorNotExists('[data-dashboard-stat] :contains("Editorial team")');
    }

    public function testAnalyticsAndCsvAreOwnerScopedEvenAfterSitewideCacheWarmup(): void
    {
        $client = self::createClient();
        $this->seed();
        $editor = $this->user('ROLE_EDITOR');
        $author = $this->user('ROLE_AUTHOR');
        $other = $this->article($editor, ArticleStatus::Published, 'Other author confidential analytics');
        $own = $this->article($author, ArticleStatus::Published, 'My author analytics');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        foreach ([[$other, 1234], [$own, 7]] as [$article, $views]) {
            $em->persist((new ArticleDailyStat())->setArticle($article)->setStatDate(new \DateTimeImmutable('today'))->setViews($views));
        }
        $em->flush();
        $client->loginUser($editor);
        $client->request('GET', '/admin/analytics');
        self::assertSelectorTextContains('main', 'Other author confidential analytics');
        $client->loginUser($author);
        foreach (['/admin/analytics', '/admin/analytics/export', '/admin'] as $path) {
            $client->request('GET', $path);
            self::assertResponseIsSuccessful();
            self::assertStringNotContainsString('Other author confidential analytics', $client->getInternalResponse()->getContent());
            self::assertStringContainsString('My author analytics', $client->getInternalResponse()->getContent());
        }
    }

    public function testTotpWorksOnTheSameInstanceAfterInsertUpdateAndReload(): void
    {
        self::createClient();
        $this->seed();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $authenticator = self::getContainer()->get(TotpAuthenticatorInterface::class);
        $user = (new User())->setEmail('totp-regression@example.test')->setDisplayName('TOTP regression')->setTotpSecret(self::SECRET);
        $em->persist($user);
        $em->flush();
        $id = $user->getId();
        foreach (range(1, 3) as $index) {
            self::assertSame(self::SECRET, $user->getTotpSecret());
            self::assertTrue($authenticator->checkCode($user, \OTPHP\TOTP::createFromSecret(self::SECRET)->now()));
            $stored = (string) $em->getConnection()->fetchOne('SELECT totp_secret FROM app_user WHERE id = ?', [$id]);
            self::assertStringStartsWith('enc:v1:', $stored);
            self::assertStringNotContainsString(self::SECRET, $stored);
            $user->setBio('Update '.$index);
            $em->flush();
        }
        $em->clear();
        $user = self::getContainer()->get(UserRepository::class)->find($id);
        self::assertTrue($authenticator->checkCode($user, \OTPHP\TOTP::createFromSecret(self::SECRET)->now()));
        $stored = $em->getConnection()->fetchOne('SELECT totp_secret FROM app_user WHERE id = ?', [$id]);
        $em->flush();
        self::assertSame($stored, $em->getConnection()->fetchOne('SELECT totp_secret FROM app_user WHERE id = ?', [$id]));
        $user->setTotpSecret(null);
        $em->flush();
        self::assertNull($user->getTotpSecret());
        self::assertNull($em->getConnection()->fetchOne('SELECT totp_secret FROM app_user WHERE id = ?', [$id]));
    }

    #[DataProvider('elevatedRoles')]
    public function testMandatoryTwoFactorAppliesToBothAdministrativeRoles(string $code): void
    {
        self::createClient();
        $this->seed();
        InMemorySettingsService::setOverride('security.require_admin_2fa', true);
        $this->expectException(CustomUserMessageAccountStatusException::class);
        self::getContainer()->get(UserChecker::class)->checkPreAuth($this->user($code));
    }

    public function testMandatoryTwoFactorCannotBeDisabledOrReplaced(): void
    {
        $client = self::createClient();
        $this->seed();
        $user = $this->user('ROLE_SUPER_ADMIN');
        $id = $user->getId();
        self::getContainer()->get(EntityManagerInterface::class)->flush();
        $client->loginUser($user);
        $crawler = $client->request('GET', '/profile');
        $token = (string) $crawler->filter('form[action="/profile/2fa/start"] input[name="_token"]')->attr('value');
        $this->user('ROLE_SUPER_ADMIN')->setTotpSecret(self::SECRET);
        self::getContainer()->get(EntityManagerInterface::class)->flush();
        $client->request('POST', '/profile/2fa/start', ['_token' => $token]);
        self::assertResponseStatusCodeSame(403);
        InMemorySettingsService::setOverride('security.require_admin_2fa', true);
        $client->request('POST', '/profile/2fa/disable');
        self::assertResponseStatusCodeSame(403);
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertSame(self::SECRET, self::getContainer()->get(UserRepository::class)->find($id)->getTotpSecret());
    }

    public function testOptionalTwoFactorDisableRequiresPasswordAndAuthenticatorCode(): void
    {
        $client = self::createClient();
        $this->seed();
        $user = $this->user('ROLE_AUTHOR')->setTotpSecret(self::SECRET);
        $id = $user->getId();
        self::getContainer()->get(EntityManagerInterface::class)->flush();
        $client->loginUser($user);
        $crawler = $client->request('GET', '/profile');
        $form = $crawler->selectButton('Disable two-factor authentication')->form();
        $client->submit($form, ['two_factor_disable_form[currentPassword]' => 'wrong', 'two_factor_disable_form[code]' => \OTPHP\TOTP::createFromSecret(self::SECRET)->now()]);
        self::assertResponseStatusCodeSame(422);
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertSame(self::SECRET, self::getContainer()->get(UserRepository::class)->find($id)->getTotpSecret());
        $crawler = $client->request('GET', '/profile');
        $client->submit($crawler->selectButton('Disable two-factor authentication')->form(), [
            'two_factor_disable_form[currentPassword]' => 'fieldnotes-demo',
            'two_factor_disable_form[code]' => \OTPHP\TOTP::createFromSecret(self::SECRET)->now(),
        ]);
        self::assertResponseStatusCodeSame(302);
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertNull(self::getContainer()->get(UserRepository::class)->find($id)->getTotpSecret());
    }

    private function user(string $code): User
    {
        foreach (self::getContainer()->get(UserRepository::class)->findAll() as $user) {
            if ($user->hasRoleCode($code)) {
                return $user;
            }
        }
        throw new \LogicException('Missing fixture role: '.$code);
    }

    private function article(User $author, ArticleStatus $status, string $title): Article
    {
        $article = (new Article())->setAuthor($author)->setTitle($title)->setContent('<p>Regression body.</p>')->setStatus($status);
        if (ArticleStatus::Published === $status) {
            $article->setPublishedAt(new \DateTimeImmutable('-1 minute'));
        }
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($article);
        $em->flush();

        return $article;
    }
}
