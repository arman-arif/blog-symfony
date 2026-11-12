<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Permission;
use App\Entity\Role;
use App\Security\PermissionCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:security:sync-permissions', description: 'Create or update the default permissions and roles idempotently.')]
final class SyncPermissionsCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $permissionRepository = $this->entityManager->getRepository(Permission::class);
        $roleRepository = $this->entityManager->getRepository(Role::class);

        $permissions = [];
        foreach (PermissionCatalog::permissions() as $code => $definition) {
            $permission = $permissionRepository->findOneBy(['code' => $code]) ?? new Permission();
            $permission->setCode($code)->setLabel($definition['label'])->setGroupName($definition['group']);
            $this->entityManager->persist($permission);
            $permissions[$code] = $permission;
        }

        foreach (PermissionCatalog::roles() as $code => $definition) {
            $role = $roleRepository->findOneBy(['code' => $code]) ?? new Role();
            $role->setCode($code)
                ->setName($definition['name'])
                ->setDescription($definition['description'])
                ->setIsSystem(true);

            foreach ($role->getPermissions() as $existing) {
                $role->removePermission($existing);
            }
            foreach ($definition['permissions'] as $permissionCode) {
                $role->addPermission($permissions[$permissionCode]);
            }

            $this->entityManager->persist($role);
        }

        $this->entityManager->flush();

        $io->success(sprintf('Synced %d permissions and %d default roles.', count($permissions), count(PermissionCatalog::roles())));

        return Command::SUCCESS;
    }
}
