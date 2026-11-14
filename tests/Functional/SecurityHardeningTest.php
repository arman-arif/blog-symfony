<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Repository\UserRepository;
use App\Tests\Support\InMemorySettingsService;
use Doctrine\ORM\EntityManagerInterface;

final class SecurityHardeningTest extends FrontTestCase
{
    public function testSecurityHeadersAndCspNoncesAreConsistent(): void
    {
        $client = self::createClient();
        $this->seed();
        $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        $headers = $client->getResponse()->headers;
        $csp = (string) $headers->get('Content-Security-Policy');
        self::assertStringContainsString("script-src 'self' 'nonce-", $csp);
        self::assertStringContainsString("frame-ancestors 'self'", $csp);
        self::assertSame('nosniff', $headers->get('X-Content-Type-Options'));
        self::assertSame('strict-origin-when-cross-origin', $headers->get('Referrer-Policy'));
        self::assertStringContainsString('camera=()', (string) $headers->get('Permissions-Policy'));
        preg_match('/nonce-([A-Za-z0-9_-]+)/', $csp, $matches);
        self::assertNotEmpty($matches[1] ?? null);
        self::assertStringContainsString('nonce="'.$matches[1].'"', (string) $client->getResponse()->getContent());
    }

    public function testPublicPagesAdvertiseSharedCachingButAdminDoesNot(): void
    {
        $client = self::createClient();
        $this->seed();
        $client->request('GET', '/robots.txt');
        self::assertResponseIsSuccessful();
        $cacheControl = (string) $client->getResponse()->headers->get('Cache-Control');
        self::assertStringContainsString('public', $cacheControl);
        self::assertStringContainsString('s-maxage=300', $cacheControl);

        $admin = self::getContainer()->get(UserRepository::class)->findOneByEmail('writer1@example.test');
        self::assertNotNull($admin);
        $client->loginUser($admin);
        $client->request('GET', '/admin');
        self::assertStringNotContainsString('s-maxage=300', (string) $client->getResponse()->headers->get('Cache-Control'));
    }

    public function testWeakPasswordIsRejectedAndStrongPasswordAccepted(): void
    {
        $client = self::createClient();
        $this->seed();
        InMemorySettingsService::setOverride('registration.enabled', true);
        $crawler = $client->request('GET', '/register');
        $client->submit($crawler->selectButton('Create account')->form([
            'registration_form[displayName]' => 'Policy Tester',
            'registration_form[email]' => 'policy.tester@example.test',
            'registration_form[plainPassword][first]' => 'password123',
            'registration_form[plainPassword][second]' => 'password123',
        ]));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', '12 characters or more');

        $crawler = $client->request('GET', '/register');
        $client->submit($crawler->selectButton('Create account')->form([
            'registration_form[displayName]' => 'Policy Tester',
            'registration_form[email]' => 'policy.tester@example.test',
            'registration_form[plainPassword][first]' => 'a-strong-uncommon-password',
            'registration_form[plainPassword][second]' => 'a-strong-uncommon-password',
        ]));
        self::assertResponseStatusCodeSame(302);
        self::assertNotNull(self::getContainer()->get(UserRepository::class)->findOneByEmail('policy.tester@example.test'));
    }

    public function testTwoFactorSecretsAreEncryptedAtRestAndDecryptedOnLoad(): void
    {
        self::createClient();
        $this->seed();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $user = self::getContainer()->get(UserRepository::class)->findOneByEmail('writer1@example.test');
        self::assertNotNull($user);
        $secret = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';
        $user->setTotpSecret($secret);
        $em->flush();
        $raw = (string) $em->getConnection()->fetchOne('SELECT totp_secret FROM app_user WHERE id = ?', [$user->getId()]);
        self::assertStringStartsWith('enc:v1:', $raw);
        self::assertStringNotContainsString($secret, $raw);

        $em->clear();
        $reloaded = self::getContainer()->get(UserRepository::class)->find($user->getId());
        self::assertNotNull($reloaded);
        self::assertSame($secret, $reloaded->getTotpSecret());
        self::assertTrue($reloaded->isTotpAuthenticationEnabled());
    }
}
