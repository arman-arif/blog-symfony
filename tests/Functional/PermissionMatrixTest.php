<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Permission;
use App\Entity\Role;
use App\Entity\User;
use App\Security\PermissionCatalog;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\TerminableInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class PermissionMatrixTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    /** @return iterable<string, array{string, array<string, int>}> */
    public static function roles(): iterable
    {
        yield 'super administrator' => ['ROLE_SUPER_ADMIN', [
            '/admin' => 200, '/admin/articles' => 200, '/admin/categories' => 200, '/admin/users' => 200,
            '/admin/roles' => 200, '/admin/permissions' => 200, '/admin/settings' => 200, '/admin/settings/smtp' => 200,
            '/admin/analytics' => 200, '/admin/newsletter' => 200, '/admin/activity' => 200, '/admin/pages' => 200,
            '/admin/profile' => 302, '/profile' => 200,
        ]];
        yield 'administrator' => ['ROLE_ADMIN', [
            '/admin' => 200, '/admin/articles' => 200, '/admin/categories' => 200, '/admin/users' => 200,
            '/admin/roles' => 403, '/admin/permissions' => 403, '/admin/settings' => 200, '/admin/settings/smtp' => 403,
            '/admin/analytics' => 200, '/admin/newsletter' => 200, '/admin/activity' => 200, '/admin/pages' => 200,
            '/admin/profile' => 302, '/profile' => 200,
        ]];
        yield 'editor' => ['ROLE_EDITOR', [
            '/admin' => 200, '/admin/articles' => 200, '/admin/categories' => 200, '/admin/users' => 403,
            '/admin/roles' => 403, '/admin/permissions' => 403, '/admin/settings' => 403, '/admin/settings/smtp' => 403,
            '/admin/analytics' => 200, '/admin/newsletter' => 403, '/admin/activity' => 403, '/admin/pages' => 403,
            '/admin/profile' => 302, '/profile' => 200,
        ]];
        yield 'author' => ['ROLE_AUTHOR', [
            '/admin' => 200, '/admin/articles' => 200, '/admin/categories' => 403, '/admin/users' => 403,
            '/admin/roles' => 403, '/admin/permissions' => 403, '/admin/settings' => 403, '/admin/settings/smtp' => 403,
            '/admin/analytics' => 200, '/admin/newsletter' => 403, '/admin/activity' => 403, '/admin/pages' => 403,
            '/admin/profile' => 302, '/profile' => 200,
        ]];
        yield 'contributor' => ['ROLE_CONTRIBUTOR', [
            '/admin' => 200, '/admin/articles' => 200, '/admin/categories' => 403, '/admin/users' => 403,
            '/admin/roles' => 403, '/admin/permissions' => 403, '/admin/settings' => 403, '/admin/settings/smtp' => 403,
            '/admin/analytics' => 403, '/admin/newsletter' => 403, '/admin/activity' => 403, '/admin/pages' => 403,
            '/admin/profile' => 302, '/profile' => 200,
        ]];
    }

    /** @param array<string, int> $expected */
    #[DataProvider('roles')]
    public function testRoleCanReachOnlyItsPermittedAdminRoutes(string $roleCode, array $expected): void
    {
        $client = self::createClient();
        $client->loginUser($this->createUserWithRole($roleCode));

        foreach ($expected as $path => $status) {
            $client->request('GET', $path);
            self::assertSame($status, $client->getResponse()->getStatusCode(), $roleCode.' → '.$path);
        }
    }

    public function testAnonymousVisitorsCannotOpenTheWorkspace(): void
    {
        self::ensureKernelShutdown();
        $kernel = self::bootKernel();
        self::assertInstanceOf(TerminableInterface::class, $kernel);
        $request = \Symfony\Component\HttpFoundation\Request::create('/admin');
        $response = $kernel->handle($request);

        self::assertSame(302, $response->getStatusCode());
        self::assertStringContainsString('/login', (string) $response->headers->get('Location'));
        $kernel->terminate($request, $response);
    }

    public function testNavigationOnlyShowsPermittedModules(): void
    {
        $client = self::createClient();
        $client->loginUser($this->createUserWithRole('ROLE_CONTRIBUTOR'));
        $client->request('GET', '/admin');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('nav[aria-label="Admin navigation"] a[href="/admin/articles"]');
        self::assertSelectorExists('nav[aria-label="Admin navigation"] a[href="/profile"]');
        self::assertSelectorNotExists('nav[aria-label="Admin navigation"] a[href="/admin/users"]');
        self::assertSelectorNotExists('nav[aria-label="Admin navigation"] a[href="/admin/settings"]');
        self::assertSelectorNotExists('nav[aria-label="Admin navigation"] a[href="/admin/roles"]');
    }

    private function createUserWithRole(string $roleCode): User
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $definition = PermissionCatalog::roles()[$roleCode];

        $permissions = [];
        foreach (PermissionCatalog::permissions() as $code => $meta) {
            $permission = (new Permission())->setCode($code)->setLabel($meta['label'])->setGroupName($meta['group']);
            $entityManager->persist($permission);
            $permissions[$code] = $permission;
        }

        $role = (new Role())
            ->setName($definition['name'])
            ->setCode($roleCode)
            ->setDescription($definition['description'])
            ->setIsSystem(true);
        foreach ($definition['permissions'] as $code) {
            $role->addPermission($permissions[$code]);
        }

        $user = (new User())
            ->setEmail(strtolower($roleCode).'-'.bin2hex(random_bytes(4)).'@example.test')
            ->setDisplayName($definition['name'].' Tester')
            ->setIsActive(true)
            ->setIsVerified(true)
            ->addAssignedRole($role);
        $user->setPassword($hasher->hashPassword($user, 'a-long-test-password'));

        $entityManager->persist($role);
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
