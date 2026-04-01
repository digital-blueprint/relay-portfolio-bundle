<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Console;

use Dbp\Relay\PortfolioBundle\Service\WorkflowService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CreateWorkflowCommand extends Command
{
    public function __construct(
        private readonly WorkflowService $workflowService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('dbp:relay:portfolio:create-workflow')
            ->setDescription('Create a new workflow instance')
            ->addArgument('type', InputArgument::REQUIRED, 'The workflow type identifier')
            ->addOption('state', null, InputOption::VALUE_REQUIRED, 'Initial custom state as a JSON string', '{}');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $type = $input->getArgument('type');
        $stateJson = $input->getOption('state');

        try {
            $customState = json_decode($stateJson, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \InvalidArgumentException('--state must be a valid JSON object: '.$e->getMessage(), previous: $e);
        }

        if (!is_array($customState) || array_is_list($customState)) {
            throw new \InvalidArgumentException('--state must be a JSON object, e.g. \'{"key":"value"}\'');
        }

        $workflow = $this->workflowService->createWorkflow($type, $customState);

        $output->writeln($workflow->getId());

        return Command::SUCCESS;
    }
}
