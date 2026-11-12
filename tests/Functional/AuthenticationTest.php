<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\ActivityLog;
use App\Entity\Role;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\Support\InMemorySettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class AuthenticationTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

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

    public function testSuccessfulLoginUpdatesLastLoginAndAuditLog(): void
    {
        $client = self::createClient();
        $user = $this->createAdminUser('correct horse battery staple');

        $crawler = $client->request('GET', '/login');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Sign in')->form([
            '_username' => $user->getEmail(),
            '_password' => 'correct horse battery staple',
        ]);
        $client->submit($form);

        self::assertResponseStatusCodeSame(302);
        self::assertStringContainsString('/admin', (string) $client->getResponse()->headers->get('Location'));

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $reloaded = self::getContainer()->get(UserRepository::class)->findOneByEmail($user->getEmail());
        self::assertNotNull($reloaded);
        self::assertNotNull($reloaded->getLastLoginAt());

        $log = $entityManager->getRepository(ActivityLog::class)->findOneBy(['action' => 'user.login']);
        self::assertNotNull($log);
    }

    public function testWrongPasswordIsRejected(): void
    {
        $client = self::createClient();
        $user = $this->createAdminUser('correct horse battery staple');

        $crawler = $client->request('GET', '/login');
        $client->submit($crawler->selectButton('Sign in')->form([
            '_username' => $user->getEmail(),
            '_password' => 'incorrect password',
        ]));

        self::assertResponseStatusCodeSame(302);
        $client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'Invalid credentials');
    }

    public function testInactiveAccountsAreBlocked(): void
    {
        $client = self::createClient();
        $user = $this->createAdminUser('correct horse battery staple');
        $user->setIsActive(false);
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        $crawler = $client->request('GET', '/login');
        $client->submit($crawler->selectButton('Sign in')->form([
            '_username' => $user->getEmail(),
            '_password' => 'correct horse battery staple',
        ]));

        $client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'account is not active');
    }

    public function testLogoutInvalidatesTheSession(): void
    {
        $client = self::createClient();
        $user = $this->createAdminUser('correct horse battery staple');
        $client->loginUser($user);

        $crawler = $client->request('GET', '/admin');
        self::assertResponseIsSuccessful();
        $client->submit($crawler->filter('form[action="/logout"]')->form());
        self::assertResponseStatusCodeSame(302);

        $client->request('GET', '/admin');
        self::assertResponseStatusCodeSame(302);
        self::assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));
    }

    public function testRegistrationIsDisabledByDefault(): void
    {
        $client = self::createClient();
        $client->request('GET', '/register');
        self::assertResponseStatusCodeSame(404);
    }

    public function testRegistrationSendsVerificationAndConfirmingItMarksTheUserVerified(): void
    {
        $client = self::createClient();
        $settings = self::getContainer()->get(InMemorySettingsService::class);
        $settings->set('registration.enabled', true);

        $crawler = $client->request('GET', '/register');
        $client->submit($crawler->selectButton('Create account')->form([
            'registration_form[displayName]' => 'New Reader',
            'registration_form[email]' => 'new.reader@example.test',
            'registration_form[plainPassword][first]' => 'a-long-reader-password',
            'registration_form[plainPassword][second]' => 'a-long-reader-password',
        ]));

        self::assertResponseStatusCodeSame(302);
        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertSame('Confirm your Fieldnotes account', $email->getSubject());

        $body = html_entity_decode((string) $email->getHtmlBody());
        self::assertMatchesRegularExpression('#/verify/email\?[^"\s]+#', $body);
        preg_match('#/verify/email\?[^"\s]+#', $body, $matches);

        $users = self::getContainer()->get(UserRepository::class);
        $user = $users->findOneByEmail('new.reader@example.test');
        self::assertNotNull($user);
        self::assertFalse($user->isVerified());

        $client->request('GET', $matches[0]);
        self::assertResponseStatusCodeSame(302);
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'email address is confirmed');

        $reloaded = self::getContainer()->get(UserRepository::class)->findOneByEmail('new.reader@example.test');
        self::assertNotNull($reloaded);
        self::assertTrue($reloaded->isVerified());
    }

    public function testPasswordResetFlowChangesThePassword(): void
    {
        $client = self::createClient();
        $user = $this->createAdminUser('old-password-value');

        $crawler = $client->request('GET', '/reset-password');
        $client->submit($crawler->selectButton('Send reset link')->form([
            'reset_password_request_form[email]' => $user->getEmail(),
        ]));
        self::assertResponseStatusCodeSame(302);
        self::assertEmailCount(1);

        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        $body = html_entity_decode((string) $email->getHtmlBody());
        self::assertMatchesRegularExpression('#/reset-password/reset/[A-Za-z0-9]{40}#', $body);
        preg_match('#/reset-password/reset/[A-Za-z0-9]{40}#', $body, $matches);

        $crawler = $client->request('GET', $matches[0]);
        self::assertResponseIsSuccessful();
        $client->submit($crawler->selectButton('Save new password')->form([
            'reset_password_form[plainPassword][first]' => 'a-brand-new-password',
            'reset_password_form[plainPassword][second]' => 'a-brand-new-password',
        ]));
        self::assertResponseStatusCodeSame(302);
        self::assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));

        $crawler = $client->request('GET', '/login');
        $client->submit($crawler->selectButton('Sign in')->form([
            '_username' => $user->getEmail(),
            '_password' => 'a-brand-new-password',
        ]));
        self::assertResponseStatusCodeSame(302);
        self::assertStringContainsString('/admin', (string) $client->getResponse()->headers->get('Location'));
    }

    public function testRequiredAdminTwoFactorBlocksLoginUntilEnrolled(): void
    {
        $client = self::createClient();
        $user = $this->createAdminUser('correct horse battery staple');
        InMemorySettingsService::setOverride('security.require_admin_2fa', true);

        $crawler = $client->request('GET', '/login');
        $client->submit($crawler->selectButton('Sign in')->form([
            '_username' => $user->getEmail(),
            '_password' => 'correct horse battery staple',
        ]));

        $client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'Two-factor authentication is required');
    }

    public function testTwoFactorEnrollmentAndLoginChallenge(): void
    {
        $client = self::createClient();
        $user = $this->createAdminUser('correct horse battery staple');
        $client->loginUser($user);

        $crawler = $client->request('GET', '/profile');
        self::assertSelectorExists('form[action="/profile/2fa/start"]');
        $token = (string) $crawler->filter('form[action="/profile/2fa/start"] input[name="_token"]')->attr('value');
        $client->request('POST', '/profile/2fa/start', ['_token' => $token]);
        self::assertResponseStatusCodeSame(302);

        $crawler = $client->followRedirect();
        $secret = trim($crawler->filter('[data-totp-secret]')->text());
        self::assertNotSame('', $secret);
        $otp = \OTPHP\TOTP::createFromSecret($secret);

        $token = (string) $crawler->filter('form[action="/profile/2fa/confirm"] input[name="_token"]')->attr('value');
        $client->request('POST', '/profile/2fa/confirm', ['_token' => $token, 'code' => $otp->now()]);
        self::assertResponseStatusCodeSame(302);
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Two-factor authentication is now enabled');

        $reloaded = self::getContainer()->get(UserRepository::class)->findOneByEmail($user->getEmail());
        self::assertNotNull($reloaded);
        self::assertNotNull($reloaded->getTotpSecret(), 'The TOTP secret must persist before the login challenge is tested.');

        self::ensureKernelShutdown();
        $client = self::createClient();
        $crawler = $client->request('GET', '/login');
        self::assertSelectorExists('#username');
        $client->submit($crawler->selectButton('Sign in')->form([
            '_username' => $user->getEmail(),
            '_password' => 'correct horse battery staple',
        ]));
        self::assertResponseStatusCodeSame(302);
        $crawler = $client->followRedirect();
        if ($client->getResponse()->isRedirect()) {
            $crawler = $client->followRedirect();
        }
        self::assertStringContainsString('/2fa', $client->getRequest()->getUri());
        self::assertSelectorExists('#auth-code');
        $client->submit($crawler->selectButton('Verify and continue')->form(['_auth_code' => $otp->now()]));
        self::assertResponseStatusCodeSame(302);
        self::assertStringContainsString('/admin', (string) $client->getResponse()->headers->get('Location'));
    }

    private function createAdminUser(string $password): User
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $role = (new Role())
            ->setName('Administrator')
            ->setCode('ROLE_SUPER_ADMIN')
            ->setIsSystem(true);
        $user = (new User())
            ->setEmail('admin-'.bin2hex(random_bytes(5)).'@example.test')
            ->setDisplayName('Test Administrator')
            ->setIsActive(true)
            ->setIsVerified(true)
            ->addAssignedRole($role);
        $user->setPassword($hasher->hashPassword($user, $password));

        $entityManager->persist($role);
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
