<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Console;

use Dbp\Relay\PortfolioBundle\Service\WorkflowService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'dbp:relay:portfolio:ping',
    description: 'Run the ping handler for all active portfolio workflows',
)]
class PingCommand extends Command
{
    public function __construct(
        private readonly WorkflowService $workflowService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Portfolio workflow ping');

        $this->workflowService->pingAll();

        $io->success('Done.');

        return Command::SUCCESS;
    }
}
