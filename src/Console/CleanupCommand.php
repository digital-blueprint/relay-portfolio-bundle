<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Console;

use Dbp\Relay\PortfolioBundle\Service\WorkflowService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CleanupCommand extends Command
{
    public function __construct(
        private readonly WorkflowService $workflowService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('dbp:relay:portfolio:cleanup')
            ->setDescription('Run the cleanup loop for all soft-deleted workflows');
    }

    protected function execute(InputInterface $console, OutputInterface $output): int
    {
        $this->workflowService->cleanupAll();
        $output->writeln('Cleanup loop finished.');

        return Command::SUCCESS;
    }
}
