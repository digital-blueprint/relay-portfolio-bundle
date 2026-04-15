<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Tests;

use Dbp\Relay\PortfolioBundle\Handler\Action;
use Dbp\Relay\PortfolioBundle\Handler\CleanupResult;
use Dbp\Relay\PortfolioBundle\Handler\StatusDisplay;
use Dbp\Relay\PortfolioBundle\Handler\WorkflowActionResult;
use Dbp\Relay\PortfolioBundle\Handler\WorkflowData;
use Dbp\Relay\PortfolioBundle\Handler\WorkflowMessage;
use Dbp\Relay\PortfolioBundle\Handler\WorkflowTypeHandlerInterface;

class DummyWorkflowTypeHandler implements WorkflowTypeHandlerInterface
{
    public const TYPE = 'dummy-test';
    public const ACTION_PROCEED = 'proceed';
    public const ACTION_CANCEL = 'cancel';

    public int $pingCallCount = 0;
    public int $cleanupCallCount = 0;
    public bool $cleanupDone = true;
    public bool $canUse = true;
    /** @var string[] IDs for which canUse() returns false */
    public array $blockedIds = [];

    public function create(array $input): array
    {
        return $input;
    }

    public function getType(): string
    {
        return self::TYPE;
    }

    public function getName(WorkflowData $workflow, string $lang): string
    {
        return 'Dummy Workflow';
    }

    public function getDescription(WorkflowData $workflow, string $lang): string
    {
        return 'A test workflow';
    }

    public function getStatusDisplay(WorkflowData $workflow, string $lang): StatusDisplay
    {
        return new StatusDisplay('Pending', 'Waiting for input');
    }

    public function canUse(WorkflowData $workflow): bool
    {
        if (in_array($workflow->getId(), $this->blockedIds, true)) {
            return false;
        }

        return $this->canUse;
    }

    public function getAvailableActions(WorkflowData $workflow, string $lang): array
    {
        return [
            new Action(self::ACTION_PROCEED, 'Proceed'),
            new Action(self::ACTION_CANCEL, 'Cancel'),
        ];
    }

    public function handleAction(WorkflowData $workflow, string $action, array $payload, string $lang): WorkflowActionResult
    {
        if ($action === self::ACTION_PROCEED) {
            return new WorkflowActionResult(
                internalState: ['step' => 'done'],
                close: true,
                message: new WorkflowMessage(
                    type: WorkflowMessage::TYPE_INFO,
                    title: 'Step completed',
                    text: 'The workflow has been completed successfully.',
                ),
            );
        }

        return new WorkflowActionResult(
            internalState: [],
            close: true,
        );
    }

    public function getExpectedTasks(WorkflowData $workflow): array
    {
        // Return one task while active, none otherwise
        if ($workflow->isActive()) {
            return ['task-'.$workflow->getId()];
        }

        return [];
    }

    public function getTaskResponse(WorkflowData $workflow, string $taskId, string $lang): array
    {
        return ['info' => 'computed from '.$workflow->getId()];
    }

    public function ping(WorkflowData $workflow): ?WorkflowActionResult
    {
        ++$this->pingCallCount;

        return null;
    }

    public function cleanup(WorkflowData $workflow): CleanupResult
    {
        ++$this->cleanupCallCount;

        return new CleanupResult(done: $this->cleanupDone, internalState: $workflow->getInternalState());
    }
}
