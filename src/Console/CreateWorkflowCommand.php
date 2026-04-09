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
            ->addOption('input', null, InputOption::VALUE_REQUIRED, 'Workflow input as a JSON string', '{}');
    }

    protected function execute(InputInterface $console, OutputInterface $output): int
    {
        $type = $console->getArgument('type');
        $inputJson = $console->getOption('input');

        try {
            $input = json_decode($inputJson, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \InvalidArgumentException('--input must be a valid JSON object: '.$e->getMessage(), previous: $e);
        }

        if (!is_array($input) || (count($input) > 0 && array_is_list($input))) {
            throw new \InvalidArgumentException('--input must be a JSON object, e.g. \'{"key":"value"}\'');
        }

        $workflow = $this->workflowService->createWorkflow($type, $input);

        $output->writeln($workflow->getId());

        return Command::SUCCESS;
    }
}
