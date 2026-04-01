<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Console;

use Dbp\Relay\PortfolioBundle\Service\WorkflowService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TriggerActionCommand extends Command
{
    public function __construct(
        private readonly WorkflowService $workflowService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('dbp:relay:portfolio:trigger-action')
            ->setDescription('Trigger a workflow action')
            ->addArgument('workflow-id', InputArgument::REQUIRED, 'The workflow ID')
            ->addArgument('action', InputArgument::REQUIRED, 'The action identifier')
            ->addOption('payload', null, InputOption::VALUE_REQUIRED, 'Action payload as a JSON string', '{}');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workflowId = $input->getArgument('workflow-id');
        $action = $input->getArgument('action');
        $payloadJson = $input->getOption('payload');

        try {
            $payload = json_decode($payloadJson, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \InvalidArgumentException('--payload must be a valid JSON object: '.$e->getMessage(), previous: $e);
        }

        if (!is_array($payload) || array_is_list($payload)) {
            throw new \InvalidArgumentException('--payload must be a JSON object, e.g. \'{"key":"value"}\'');
        }

        $result = $this->workflowService->handleAction($workflowId, $action, $payload);

        if ($result->getResponseData() !== []) {
            $output->writeln(json_encode($result->getResponseData(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        }

        return Command::SUCCESS;
    }
}
