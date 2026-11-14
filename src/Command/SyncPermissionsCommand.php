<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\User\PermissionSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:security:sync-permissions', description: 'Create missing catalog permissions and default roles without changing existing access policy.')]
final class SyncPermissionsCommand extends Command
{
    public function __construct(private readonly PermissionSyncService $permissions)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $created = $this->permissions->sync();
        $io->success(sprintf('Created %d missing permissions and %d missing default roles. Existing roles, grants and metadata were preserved.', $created['permissions'], $created['roles']));

        return Command::SUCCESS;
    }
}
