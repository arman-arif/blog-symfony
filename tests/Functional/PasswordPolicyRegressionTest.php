<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Repository\UserRepository;
use App\Service\Settings\DatabaseSettingsService;
use App\Service\User\UserManagementService;
use App\Tests\Support\PasswordBreachHttpClient;
use App\Validator\StrongPassword;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

final class PasswordPolicyRegressionTest extends FrontTestCase
{
    private const string COMPROMISED = 'Reported-fixture-passphrase-9!';

    protected function tearDown(): void
    {
        PasswordBreachHttpClient::configureFixture();
        parent::tearDown();
    }

    /** @return iterable<string, array{string, int}> */
    public static function rejectedPasswords(): iterable
    {
        yield 'repeated' => ['aaaaaaaaaaaa', 200];
        yield 'repeated sequence' => ['qwertyqwertyqwerty', 200];
        yield 'reported breach' => [self::COMPROMISED, 200];
        yield 'lookup unavailable' => ['Unreported-fixture-passphrase-9!', 503];
    }

    #[DataProvider('rejectedPasswords')]
    public function testRegistrationRejectsWeakBreachedAndUnverifiablePasswords(string $password, int $status): void
    {
        $client = self::createClient();
        $this->seed();
        self::getContainer()->get(DatabaseSettingsService::class)->saveGroup('general', ['registration_enabled' => true]);
        PasswordBreachHttpClient::configureFixture(self::COMPROMISED, $status);
        $crawler = $client->request('GET', '/register');
        $client->submit($crawler->selectButton('Create account')->form([
            'registration_form[displayName]' => 'Policy Reader', 'registration_form[email]' => 'policy@example.test',
            'registration_form[plainPassword][first]' => $password, 'registration_form[plainPassword][second]' => $password,
        ]));
        self::assertResponseStatusCodeSame(422);
        self::assertNull(self::getContainer()->get(UserRepository::class)->findOneByEmail('policy@example.test'));
        if (503 === $status) {
            self::assertSelectorTextContains('main', 'could not be verified');
        } elseif (self::COMPROMISED === $password) {
            self::assertSelectorTextContains('main', 'data breach');
        } else {
            self::assertSame([], PasswordBreachHttpClient::$requests, 'Weak inputs must not trigger network work.');
        }
    }

    public function testOnlyPaddedFiveCharacterHashPrefixIsSentAndSafePasswordPasses(): void
    {
        self::createClient();
        $password = 'Unreported-fixture-passphrase-9!';
        PasswordBreachHttpClient::configureFixture();
        self::assertCount(0, self::getContainer()->get(ValidatorInterface::class)->validate($password, new StrongPassword()));
        self::assertCount(1, PasswordBreachHttpClient::$requests);
        $request = PasswordBreachHttpClient::$requests[0];
        self::assertSame('GET', $request['method']);
        self::assertSame('https://api.pwnedpasswords.com/range/'.substr(strtoupper(sha1($password)), 0, 5), $request['url']);
        self::assertStringNotContainsString($password, json_encode($request, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString(strtoupper(sha1($password)), json_encode($request, JSON_THROW_ON_ERROR));
        self::assertContains('Add-Padding: true', $request['headers']);
    }

    #[DataProvider('rejectedPasswords')]
    public function testResetAndProfileRejectUnsafePasswordsWithoutChangingStoredHash(string $password, int $status): void
    {
        $client = self::createClient();
        $this->seed();
        $user = self::getContainer()->get(UserRepository::class)->findOneByEmail('writer1@example.test');
        $id = $user->getId();
        $before = $user->getPassword();
        $token = self::getContainer()->get(ResetPasswordHelperInterface::class)->generateResetToken($user)->getToken();
        PasswordBreachHttpClient::configureFixture(self::COMPROMISED, $status);
        $crawler = $client->request('GET', '/reset-password/reset/'.$token);
        $client->submit($crawler->selectButton('Save new password')->form([
            'reset_password_form[plainPassword][first]' => $password,
            'reset_password_form[plainPassword][second]' => $password,
        ]));
        self::assertResponseStatusCodeSame(422);
        $message = 503 === $status ? 'could not be verified' : (self::COMPROMISED === $password ? 'data breach' : 'higher-entropy');
        self::assertSelectorTextContains('main', $message);
        self::assertSame($before, self::getContainer()->get(UserRepository::class)->find($id)->getPassword());
        $client->loginUser(self::getContainer()->get(UserRepository::class)->find($id));
        $crawler = $client->request('GET', '/profile');
        $client->submit($crawler->selectButton('Update password')->form([
            'change_password_form[currentPassword]' => 'fieldnotes-demo',
            'change_password_form[newPassword][first]' => $password,
            'change_password_form[newPassword][second]' => $password,
        ]));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', $message);
        self::getContainer()->get(EntityManagerInterface::class)->flush();
        self::assertSame($before, self::getContainer()->get(UserRepository::class)->find($id)->getPassword());
    }

    #[DataProvider('rejectedPasswords')]
    public function testAdminFormServiceAndBootstrapCannotBypassPasswordPolicy(string $password, int $status): void
    {
        $client = self::createClient();
        $this->seed();
        $user = self::getContainer()->get(UserRepository::class)->findOneByEmail('writer1@example.test');
        $client->loginUser($user);
        $id = $user->getId();
        $before = $user->getPassword();
        PasswordBreachHttpClient::configureFixture(self::COMPROMISED, $status);
        $crawler = $client->request('GET', '/admin/users/'.$id.'/edit');
        $form = $crawler->selectButton('Save user')->form();
        $form['admin_user_form[temporaryPassword]'] = $password;
        $client->submit($form);
        self::assertResponseStatusCodeSame(422);
        $message = 503 === $status ? 'could not be verified' : (self::COMPROMISED === $password ? 'data breach' : 'higher-entropy');
        self::assertSelectorTextContains('main', $message);
        $user = self::getContainer()->get(UserRepository::class)->find($id);
        try {
            self::getContainer()->get(UserManagementService::class)->save($user, $user, $user->getAssignedRoles()->toArray(), $password, false);
            self::fail('Direct service calls must enforce password policy.');
        } catch (ValidationFailedException $exception) {
            self::assertStringContainsString($message, $exception->getMessage());
            self::assertStringNotContainsString($password, $exception->getMessage());
        }
        self::getContainer()->get(EntityManagerInterface::class)->flush();
        self::assertSame($before, $user->getPassword());
        $tester = new CommandTester((new Application(self::$kernel))->find('app:user:create-admin'));
        $tester->execute(['--email' => 'breached-admin@example.test', '--name' => 'Rejected Admin', '--password' => $password]);
        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString($message, $tester->getDisplay());
        self::assertNull(self::getContainer()->get(UserRepository::class)->findOneByEmail('breached-admin@example.test'));
    }

    public function testAdminServiceLookupOutageAfterFormValidationIsAnErrorNotAPartialWrite(): void
    {
        $client = self::createClient();
        $this->seed();
        $user = self::getContainer()->get(UserRepository::class)->findOneByEmail('writer1@example.test');
        $client->loginUser($user);
        $id = $user->getId();
        $before = $user->getPassword();
        $name = $user->getDisplayName();
        PasswordBreachHttpClient::configureFixture(status: 503, successfulRequestsBeforeFailure: 1);
        $crawler = $client->request('GET', '/admin/users/'.$id.'/edit');
        $form = $crawler->selectButton('Save user')->form();
        $form['admin_user_form[temporaryPassword]'] = 'Unreported-fixture-passphrase-9!';
        $form['admin_user_form[displayName]'] = 'Rejected change';
        $client->submit($form);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'could not be verified');
        self::assertCount(2, PasswordBreachHttpClient::$requests);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->flush();
        $em->clear();
        $user = self::getContainer()->get(UserRepository::class)->find($id);
        self::assertSame($before, $user->getPassword());
        self::assertSame($name, $user->getDisplayName());
    }

    public function testSafeProfilePasswordChangesPersistAndBlankAdminEditsDoNotLookUpBreaches(): void
    {
        $client = self::createClient();
        $this->seed();
        $user = self::getContainer()->get(UserRepository::class)->findOneByEmail('writer1@example.test');
        $client->loginUser($user);
        $id = $user->getId();
        PasswordBreachHttpClient::configureFixture();
        $crawler = $client->request('GET', '/profile');
        $password = 'Unreported-fixture-passphrase-9!';
        $client->submit($crawler->selectButton('Update password')->form([
            'change_password_form[currentPassword]' => 'fieldnotes-demo',
            'change_password_form[newPassword][first]' => $password,
            'change_password_form[newPassword][second]' => $password,
        ]));
        self::assertResponseRedirects('/profile');
        $user = self::getContainer()->get(UserRepository::class)->find($id);
        self::assertTrue(self::getContainer()->get(\Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface::class)->isPasswordValid($user, $password));
        PasswordBreachHttpClient::configureFixture(status: 503);
        $before = $user->getPassword();
        self::getContainer()->get(UserManagementService::class)->save($user, $user, $user->getAssignedRoles()->toArray(), '', false);
        self::assertSame($before, $user->getPassword());
        self::assertSame([], PasswordBreachHttpClient::$requests);
    }
}
