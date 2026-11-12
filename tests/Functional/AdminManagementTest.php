<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Page;
use App\Entity\User;
use App\Repository\PageRepository;
use App\Repository\RoleRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class AdminManagementTest extends FrontTestCase
{
    public function testPagesAreCreatedSanitizedPublishedAndDeleted(): void
    {
        $client = self::createClient();
        $this->seed();
        $admin = self::getContainer()->get(UserRepository::class)->findOneByEmail('writer1@example.test');
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $crawler = $client->request('GET', '/admin/pages/new');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Save page')->form();
        $form['page_form[title]'] = 'Community standards';
        $form['page_form[status]'] = 'published';
        $form['page_form[content]'] = '<p>Be thoughtful.</p><script>alert("xss")</script>';
        $client->submit($form);
        self::assertResponseStatusCodeSame(302);

        $page = self::getContainer()->get(PageRepository::class)->findOneBy(['title' => 'Community standards']);
        self::assertInstanceOf(Page::class, $page);
        self::assertStringContainsString('Be thoughtful.', $page->getContent());
        self::assertStringNotContainsString('<script', $page->getContent());
        self::assertNotNull($page->getPublishedAt());

        $client->request('GET', '/pages/'.$page->getSlug());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Be thoughtful.');

        $crawler = $client->request('GET', '/admin/pages');
        $token = (string) $crawler->filter('form[action$="/'.$page->getId().'/delete"] input[name="_token"]')->attr('value');
        $client->request('POST', '/admin/pages/'.$page->getId().'/delete', ['_token' => $token]);
        self::assertResponseStatusCodeSame(302);
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertNull(self::getContainer()->get(PageRepository::class)->find($page->getId()));
    }

    public function testAdminCanCreateAssignAndDeactivateAUser(): void
    {
        $client = self::createClient();
        $this->seed();
        $admin = self::getContainer()->get(UserRepository::class)->findOneByEmail('writer1@example.test');
        $authorRole = self::getContainer()->get(RoleRepository::class)->findOneByCode('ROLE_AUTHOR');
        self::assertNotNull($admin);
        self::assertNotNull($authorRole);
        $client->loginUser($admin);

        $crawler = $client->request('GET', '/admin/users/new');
        self::assertResponseIsSuccessful();
        $token = (string) $crawler->filter('input[name="admin_user_form[_token]"]')->attr('value');
        $client->request('POST', '/admin/users/new', ['admin_user_form' => [
            'displayName' => 'New Contributor',
            'email' => 'new.contributor@example.test',
            'jobTitle' => 'Writer',
            'bio' => '',
            'temporaryPassword' => 'temporary-password',
            'assignedRoles' => [(string) $authorRole->getId()],
            'isActive' => '1',
            '_token' => $token,
        ]]);
        self::assertResponseStatusCodeSame(302);

        $user = self::getContainer()->get(UserRepository::class)->findOneByEmail('new.contributor@example.test');
        self::assertInstanceOf(User::class, $user);
        self::assertTrue($user->hasRoleCode('ROLE_AUTHOR'));
        self::assertNotSame('temporary-password', $user->getPassword());

        $crawler = $client->request('GET', '/admin/users/'.$user->getId().'/edit');
        self::assertResponseIsSuccessful();
        $token = (string) $crawler->filter('input[name="admin_user_form[_token]"]')->attr('value');
        $client->request('POST', '/admin/users/'.$user->getId().'/edit', ['admin_user_form' => [
            'displayName' => $user->getDisplayName(),
            'email' => $user->getEmail(),
            'jobTitle' => $user->getJobTitle(),
            'bio' => $user->getBio(),
            'temporaryPassword' => '',
            'assignedRoles' => [(string) $authorRole->getId()],
            '_token' => $token,
        ]]);
        self::assertResponseStatusCodeSame(302);
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertFalse(self::getContainer()->get(UserRepository::class)->find($user->getId())?->isActive());
    }

    public function testTheLastSuperAdminCannotBeDemoted(): void
    {
        $client = self::createClient();
        $this->seed();
        $admin = self::getContainer()->get(UserRepository::class)->findOneByEmail('writer6@example.test');
        self::assertNotNull($admin);
        self::assertTrue($admin->hasRoleCode('ROLE_SUPER_ADMIN'));
        $first = self::getContainer()->get(UserRepository::class)->findOneByEmail('writer1@example.test');
        $superRole = self::getContainer()->get(RoleRepository::class)->findOneByCode('ROLE_SUPER_ADMIN');
        self::assertNotNull($first);
        self::assertNotNull($superRole);
        $first->removeAssignedRole($superRole);
        self::getContainer()->get(EntityManagerInterface::class)->flush();
        $client->loginUser($admin);

        $crawler = $client->request('GET', '/admin/users/'.$admin->getId().'/edit');
        self::assertResponseIsSuccessful();
        $token = (string) $crawler->filter('input[name="admin_user_form[_token]"]')->attr('value');
        $client->request('POST', '/admin/users/'.$admin->getId().'/edit', ['admin_user_form' => [
            'displayName' => $admin->getDisplayName(),
            'email' => $admin->getEmail(),
            'jobTitle' => $admin->getJobTitle(),
            'bio' => $admin->getBio(),
            'temporaryPassword' => '',
            'assignedRoles' => [],
            'isActive' => '1',
            'isVerified' => '1',
            '_token' => $token,
        ]]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'last Super Administrator');
        self::assertSame(1, self::getContainer()->get(UserRepository::class)->countSuperAdmins());
    }

    public function testBootstrapAdminCommandCreatesAVerifiedSuperAdminAndRejectsWeakPasswords(): void
    {
        self::createClient();
        $this->seed();
        $application = new Application(self::$kernel);
        $command = $application->find('app:user:create-admin');
        $tester = new CommandTester($command);
        $tester->execute(['--email' => 'bootstrap@example.test', '--name' => 'Bootstrap Admin', '--password' => 'short']);
        self::assertSame(1, $tester->getStatusCode());

        $tester->execute(['--email' => 'bootstrap@example.test', '--name' => 'Bootstrap Admin', '--password' => 'bootstrap-admin-password']);
        self::assertSame(0, $tester->getStatusCode());
        $user = self::getContainer()->get(UserRepository::class)->findOneByEmail('bootstrap@example.test');
        self::assertInstanceOf(User::class, $user);
        self::assertTrue($user->isVerified());
        self::assertTrue($user->hasRoleCode('ROLE_SUPER_ADMIN'));
    }

    public function testRolesPermissionsAndActivityAreReal(): void
    {
        $client = self::createClient();
        $this->seed();
        $admin = self::getContainer()->get(UserRepository::class)->findOneByEmail('writer1@example.test');
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $role = self::getContainer()->get(RoleRepository::class)->findOneByCode('ROLE_EDITOR');
        self::assertNotNull($role);
        $crawler = $client->request('GET', '/admin/roles/'.$role->getId().'/edit');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#role-form, form[name="role_form"]');
        self::assertSelectorExists('input[name="role_form[permissions][]"][checked]');

        $client->request('GET', '/admin/permissions');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('table[aria-label="Role permission matrix"]');
        self::assertSelectorExists('input[name^="permissions["][checked]');

        $client->request('GET', '/admin/activity');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('table[aria-label="Activity log"]');
    }
}
