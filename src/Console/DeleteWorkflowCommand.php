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
            ->setDescription('Soft-delete a workflow by ID, queuing it for external resource cleanup')
            ->addArgument('id', InputArgument::REQUIRED, 'The workflow ID to soft-delete');
    }

    protected function execute(InputInterface $console, OutputInterface $output): int
    {
        $id = $console->getArgument('id');

        if (!$this->workflowService->softDeleteWorkflow($id)) {
            $output->writeln(sprintf("Workflow '%s' not found or already deleted.", $id));

            return Command::FAILURE;
        }

        $output->writeln(sprintf("Workflow '%s' marked for deletion. Run cleanup to release external resources.", $id));

        return Command::SUCCESS;
    }
}
