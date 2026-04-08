<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\DummyWorkflow;

use Dbp\Relay\PortfolioBundle\ApiPlatform\WorkflowResultMessage;
use Dbp\Relay\PortfolioBundle\Handler\Action;
use Dbp\Relay\PortfolioBundle\Handler\StateDisplay;
use Dbp\Relay\PortfolioBundle\Handler\WorkflowActionResult;
use Dbp\Relay\PortfolioBundle\Handler\WorkflowData;
use Dbp\Relay\PortfolioBundle\Handler\WorkflowTypeHandlerInterface;
use Symfony\Component\Uid\Uuid;

class DummyWorkflowTypeHandler implements WorkflowTypeHandlerInterface
{
    public const TYPE = 'dummy';
    public const ACTION_COMPLETE = 'complete';
    public const ACTION_INCREMENT = 'increment';
    public const ACTION_RESET = 'reset';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function getName(WorkflowData $workflow): string
    {
        return $workflow->getCustomState()['title'] ?? 'Untitled';
    }

    public function getDescription(WorkflowData $workflow): string
    {
        return '';
    }

    public function getCurrentStateDisplay(WorkflowData $workflow): StateDisplay
    {
        $counter = $workflow->getCustomState()['counter'] ?? 0;

        return match ($workflow->getState()) {
            WorkflowData::STATE_DONE => new StateDisplay('Completed', sprintf('Workflow completed. Counter was: %d', $counter)),
            default => new StateDisplay('Waiting', sprintf('Waiting for input. Counter: %d', $counter)),
        };
    }

    public function canView(WorkflowData $workflow): bool
    {
        return true;
    }

    public function getAvailableActions(WorkflowData $workflow): array
    {
        return match ($workflow->getState()) {
            WorkflowData::STATE_ACTIVE => [
                new Action(self::ACTION_INCREMENT, 'Increment Counter'),
                new Action(self::ACTION_COMPLETE, 'Complete'),
                new Action('view', 'View Details', Action::TYPE_URL, 'https://www.youtube.com/watch?v=dQw4w9WgXcQ#'.$workflow->getId()),
            ],
            WorkflowData::STATE_DONE => [
                new Action(self::ACTION_RESET, 'Reset'),
            ],
            default => [],
        };
    }

    public function handleAction(WorkflowData $workflow, string $action, array $payload): WorkflowActionResult
    {
        $customState = $workflow->getCustomState();

        if ($action === self::ACTION_INCREMENT) {
            $customState['counter'] = ($customState['counter'] ?? 0) + ($customState['increment'] ?? 1);

            return new WorkflowActionResult(customState: $customState);
        }

        if ($action === self::ACTION_RESET) {
            $customState['counter'] = 0;

            return new WorkflowActionResult(
                customState: $customState,
                state: WorkflowData::STATE_ACTIVE,
            );
        }

        // complete
        return new WorkflowActionResult(
            customState: $customState,
            state: WorkflowData::STATE_DONE,
            message: new WorkflowResultMessage(
                type: WorkflowResultMessage::TYPE_SUCCESS,
                title: 'Workflow completed',
                text: 'The workflow has been completed successfully.',
            ),
        );
    }

    public function getExpectedTasks(WorkflowData $workflow): array
    {
        $namespace = Uuid::fromString($workflow->getId());

        return [Uuid::v5($namespace, (string) ($workflow->getCustomState()['counter'] ?? 0))->toRfc4122()];
    }

    public function getTaskResponse(WorkflowData $workflow, string $taskId): array
    {
        return [];
    }

    public function ping(WorkflowData $workflow): ?WorkflowActionResult
    {
        return null;
    }
}
