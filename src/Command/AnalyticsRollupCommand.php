<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Analytics\AnalyticsRollupService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:analytics:rollup', description: 'Aggregate article views into daily stats and prune old raw views.')]
final class AnalyticsRollupCommand extends Command
{
    public function __construct(private readonly AnalyticsRollupService $rollup)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('days', null, InputOption::VALUE_REQUIRED, 'How many recent days to aggregate.', '2')
            ->addOption('retention', null, InputOption::VALUE_REQUIRED, 'Raw view retention in days (0 disables pruning).', '30');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = max(1, (int) $input->getOption('days'));
        $retention = max(0, (int) $input->getOption('retention'));

        $rows = $this->rollup->aggregateRecent($days);
        $pruned = $retention > 0 ? $this->rollup->pruneRawViews($retention) : 0;
        $io->success(sprintf('Aggregated %d article/day rows and pruned %d raw views.', $rows, $pruned));

        return Command::SUCCESS;
    }
}
