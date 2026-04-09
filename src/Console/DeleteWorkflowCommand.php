<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Console;

use Dbp\Relay\PortfolioBundle\Service\WorkflowService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DeleteWorkflowCommand extends Command
{
    public function __construct(
        private readonly WorkflowService $workflowService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('dbp:relay:portfolio:delete-workflow')
            ->setDescription('Delete a workflow and all its tasks by ID')
            ->addArgument('id', InputArgument::REQUIRED, 'The workflow ID to delete');
    }

    protected function execute(InputInterface $console, OutputInterface $output): int
    {
        $id = $console->getArgument('id');

        if (!$this->workflowService->deleteWorkflow($id)) {
            $output->writeln(sprintf("Workflow '%s' not found.", $id));

            return Command::FAILURE;
        }

        $output->writeln(sprintf("Workflow '%s' deleted.", $id));

        return Command::SUCCESS;
    }
}
