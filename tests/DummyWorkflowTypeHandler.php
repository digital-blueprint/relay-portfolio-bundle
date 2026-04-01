<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Tests;

use Dbp\Relay\PortfolioBundle\Handler\Action;
use Dbp\Relay\PortfolioBundle\Handler\StateDisplay;
use Dbp\Relay\PortfolioBundle\Handler\WorkflowActionResult;
use Dbp\Relay\PortfolioBundle\Handler\WorkflowTypeHandlerInterface;
use Dbp\Relay\PortfolioBundle\Persistence\TaskPersistence;
use Dbp\Relay\PortfolioBundle\Persistence\WorkflowPersistence;

class DummyWorkflowTypeHandler implements WorkflowTypeHandlerInterface
{
    public const TYPE = 'dummy-test';
    public const ACTION_PROCEED = 'proceed';
    public const ACTION_CANCEL = 'cancel';

    public int $pingCallCount = 0;

    public function getType(): string
    {
        return self::TYPE;
    }

    public function getName(WorkflowPersistence $workflow): string
    {
        return 'Dummy Workflow';
    }

    public function getDescription(WorkflowPersistence $workflow): string
    {
        return 'A test workflow';
    }

    public function getCurrentStateDisplay(WorkflowPersistence $workflow): StateDisplay
    {
        return new StateDisplay('Pending', 'Waiting for input');
    }

    public function canView(WorkflowPersistence $workflow): bool
    {
        return true;
    }

    public function getAvailableActions(WorkflowPersistence $workflow): array
    {
        return [
            new Action(self::ACTION_PROCEED, 'Proceed'),
            new Action(self::ACTION_CANCEL, 'Cancel'),
        ];
    }

    public function handleAction(WorkflowPersistence $workflow, string $action, array $payload): WorkflowActionResult
    {
        if ($action === self::ACTION_PROCEED) {
            return new WorkflowActionResult(
                customState: ['step' => 'done'],
                state: WorkflowPersistence::STATE_DONE,
                responseData: ['next' => '/some/url'],
            );
        }

        return new WorkflowActionResult(
            customState: [],
            state: WorkflowPersistence::STATE_CANCELLED,
        );
    }

    public function getExpectedTasks(WorkflowPersistence $workflow): array
    {
        // Return one task while active, none otherwise
        if ($workflow->getState() === WorkflowPersistence::STATE_ACTIVE) {
            return ['task-'.$workflow->getId()];
        }

        return [];
    }

    public function getTaskResponse(TaskPersistence $task, WorkflowPersistence $workflow): array
    {
        return ['info' => 'computed from '.$workflow->getId()];
    }

    public function ping(WorkflowPersistence $workflow): ?WorkflowActionResult
    {
        ++$this->pingCallCount;

        return null;
    }
}
