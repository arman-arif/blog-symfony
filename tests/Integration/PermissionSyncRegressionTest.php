<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Permission;
use App\Entity\Role;
use App\Repository\PermissionRepository;
use App\Repository\RoleRepository;
use App\Security\PermissionCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class PermissionSyncRegressionTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    public function testFreshBootstrapCreatesTheCatalogAndRepeatingItIsANoOp(): void
    {
        self::bootKernel();
        $tester = $this->sync();
        self::assertStringContainsString(sprintf('Created %d missing permissions and %d missing default roles', count(PermissionCatalog::permissions()), count(PermissionCatalog::roles())), $tester->getDisplay());
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $roles = self::getContainer()->get(RoleRepository::class);
        foreach (PermissionCatalog::roles() as $code => $definition) {
            $role = $roles->findOneByCode($code);
            self::assertNotNull($role);
            self::assertSame($definition['name'], $role->getName());
            self::assertSame($definition['description'], $role->getDescription());
            self::assertTrue($role->isSystem());
            $expected = $definition['permissions'];
            sort($expected);
            self::assertSame($expected, $this->codes($role));
        }
        $before = $this->snapshot();
        self::assertStringContainsString('Created 0 missing permissions and 0 missing default roles', $this->sync()->getDisplay());
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertSame($before, $this->snapshot());
    }

    public function testDeploySyncPreservesCustomGrantsRevocationsAndMetadata(): void
    {
        self::bootKernel();
        $this->sync();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $permissions = self::getContainer()->get(PermissionRepository::class);
        $roles = self::getContainer()->get(RoleRepository::class);
        $custom = (new Permission())->setCode('custom.review')->setLabel('Local review')->setGroupName('Local')->setDescription('Operator owned');
        $em->persist($custom);
        $role = $roles->findOneByCode('ROLE_EDITOR');
        self::assertNotNull($role);
        $publish = $permissions->findOneByCode('article.publish');
        self::assertNotNull($publish);
        $publish->setLabel('Local publish wording')->setGroupName('Local')->setDescription('Keep this explanation');
        $role->removePermission($publish)->addPermission($custom)->setName('Local editorial team')->setDescription('Custom description')->setIsSystem(false);
        $empty = $roles->findOneByCode('ROLE_CONTRIBUTOR');
        self::assertNotNull($empty);
        $empty->getPermissions()->clear();
        $em->persist((new Role())->setCode('ROLE_CUSTOM')->setName('Custom role')->setDescription('Not in catalog')->addPermission($custom));
        $em->flush();
        $before = $this->snapshot();

        $this->sync();
        $this->sync();
        $em->clear();
        self::assertSame($before, $this->snapshot());
        self::assertFalse($roles->findOneByCode('ROLE_EDITOR')->hasPermission('article.publish'));
        self::assertTrue($roles->findOneByCode('ROLE_EDITOR')->hasPermission('custom.review'));
        self::assertSame([], $this->codes($roles->findOneByCode('ROLE_CONTRIBUTOR')));
    }

    public function testNewCatalogEntriesDoNotSilentlyGrantExistingRolesMoreAccess(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $existing = (new Role())->setCode('ROLE_EDITOR')->setName('Restricted editor');
        $em->persist($existing);
        $em->flush();
        $this->sync();
        $em->clear();
        $roles = self::getContainer()->get(RoleRepository::class);
        self::assertSame([], $this->codes($roles->findOneByCode('ROLE_EDITOR')));
        self::assertSame('Restricted editor', $roles->findOneByCode('ROLE_EDITOR')->getName());
        self::assertTrue($roles->findOneByCode('ROLE_SUPER_ADMIN')->hasPermission('article.publish'));
        self::assertCount(count(PermissionCatalog::permissions()), self::getContainer()->get(PermissionRepository::class)->findAll());
    }

    private function sync(): CommandTester
    {
        $tester = new CommandTester((new Application(self::$kernel))->find('app:security:sync-permissions'));
        self::assertSame(0, $tester->execute([], ['interactive' => false]));

        return $tester;
    }

    /** @return list<string> */
    private function codes(Role $role): array
    {
        $codes = $role->getPermissions()->map(static fn (Permission $permission): string => $permission->getCode())->toArray();
        sort($codes);

        return $codes;
    }

    /** @return array<string, mixed> */
    private function snapshot(): array
    {
        $data = [];
        foreach (self::getContainer()->get(RoleRepository::class)->findBy([], ['code' => 'ASC']) as $role) {
            $data['role:'.$role->getCode()] = [$role->getId(), $role->getName(), $role->getDescription(), $role->isSystem(), $this->codes($role)];
        }
        foreach (self::getContainer()->get(PermissionRepository::class)->findBy([], ['code' => 'ASC']) as $permission) {
            $data['permission:'.$permission->getCode()] = [$permission->getId(), $permission->getLabel(), $permission->getGroupName(), $permission->getDescription()];
        }

        return $data;
    }
}
