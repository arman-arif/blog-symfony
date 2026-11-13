<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Media\MediaDeletionService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:media:cleanup', description: 'Retry physical cleanup of deleted media originals and derivatives.')]
final class MediaCleanupCommand extends Command
{
    public function __construct(private readonly MediaDeletionService $deletion)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $counts = $this->deletion->retryPending();
        $io = new SymfonyStyle($input, $output);
        $message = sprintf('Cleaned %d media files; %d still need retrying.', $counts['cleaned'], $counts['failed']);
        if ($counts['failed'] > 0) {
            $io->error($message);

            return Command::FAILURE;
        }
        $io->success($message);

        return Command::SUCCESS;
    }
}
