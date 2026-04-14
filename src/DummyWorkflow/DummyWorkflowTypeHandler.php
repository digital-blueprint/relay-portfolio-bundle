<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\DummyWorkflow;

use Dbp\Relay\PortfolioBundle\Handler\Action;
use Dbp\Relay\PortfolioBundle\Handler\StateDisplay;
use Dbp\Relay\PortfolioBundle\Handler\WorkflowActionResult;
use Dbp\Relay\PortfolioBundle\Handler\WorkflowData;
use Dbp\Relay\PortfolioBundle\Handler\WorkflowMessage;
use Dbp\Relay\PortfolioBundle\Handler\WorkflowTypeHandlerInterface;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Uid\Uuid;

class DummyWorkflowTypeHandler implements WorkflowTypeHandlerInterface
{
    public const TYPE = 'dummy';
    public const ACTION_COMPLETE = 'complete';
    public const ACTION_INCREMENT = 'increment';
    public const ACTION_RESET = 'reset';

    private readonly Translator $translator;

    public function __construct()
    {
        $translator = new Translator('en');
        $translator->addLoader('yaml', new YamlFileLoader());
        $translator->addResource('yaml', __DIR__.'/translations/messages.en.yaml', 'en');
        $translator->addResource('yaml', __DIR__.'/translations/messages.de.yaml', 'de');
        $this->translator = $translator;
    }

    public function create(array $input): array
    {
        $input['title'] ??= [];
        $input['counter'] ??= 0;
        $input['increment'] ??= 1;

        return $input;
    }

    public function getType(): string
    {
        return self::TYPE;
    }

    public function getName(WorkflowData $workflow, string $lang): string
    {
        $titles = $workflow->getInternalState()['title'];

        return $titles[$lang] ?? $titles['en'] ?? $this->translator->trans('dummy_workflow.name.untitled', locale: $lang);
    }

    public function getDescription(WorkflowData $workflow, string $lang): string
    {
        return '';
    }

    public function getCurrentStateDisplay(WorkflowData $workflow, string $lang): StateDisplay
    {
        $counter = $workflow->getInternalState()['counter'];

        return match ($workflow->getState()) {
            WorkflowData::STATE_DONE => new StateDisplay(
                $this->translator->trans('dummy_workflow.state.completed', locale: $lang),
                $this->translator->trans('dummy_workflow.state.completed_desc', ['%count%' => $counter], locale: $lang),
            ),
            default => new StateDisplay(
                $this->translator->trans('dummy_workflow.state.waiting', locale: $lang),
                $this->translator->trans('dummy_workflow.state.waiting_desc', ['%count%' => $counter], locale: $lang),
            ),
        };
    }

    public function canUse(WorkflowData $workflow): bool
    {
        return true;
    }

    public function getAvailableActions(WorkflowData $workflow, string $lang): array
    {
        return match ($workflow->getState()) {
            WorkflowData::STATE_ACTIVE => [
                new Action(self::ACTION_INCREMENT, $this->translator->trans('dummy_workflow.action.increment', locale: $lang)),
                new Action(self::ACTION_COMPLETE, $this->translator->trans('dummy_workflow.action.complete', locale: $lang)),
                new Action('view', $this->translator->trans('dummy_workflow.action.view', locale: $lang), Action::TYPE_URL, 'https://www.youtube.com/watch?v=dQw4w9WgXcQ#'.$workflow->getId()),
            ],
            WorkflowData::STATE_DONE => [
                new Action(self::ACTION_RESET, $this->translator->trans('dummy_workflow.action.reset', locale: $lang)),
            ],
            default => [],
        };
    }

    public function handleAction(WorkflowData $workflow, string $action, array $payload, string $lang): WorkflowActionResult
    {
        $internalState = $workflow->getInternalState();

        if ($action === self::ACTION_INCREMENT) {
            $internalState['counter'] += $internalState['increment'];

            return new WorkflowActionResult(internalState: $internalState);
        }

        if ($action === self::ACTION_RESET) {
            $internalState['counter'] = 0;

            return new WorkflowActionResult(
                internalState: $internalState,
                state: WorkflowData::STATE_ACTIVE,
            );
        }

        // complete
        return new WorkflowActionResult(
            internalState: $internalState,
            state: WorkflowData::STATE_DONE,
            message: new WorkflowMessage(
                type: WorkflowMessage::TYPE_SUCCESS,
                title: $this->translator->trans('dummy_workflow.message.completed_title', locale: $lang),
                text: $this->translator->trans('dummy_workflow.message.completed_text', locale: $lang),
            ),
        );
    }

    public function getExpectedTasks(WorkflowData $workflow): array
    {
        $namespace = Uuid::fromString($workflow->getId());

        return [Uuid::v5($namespace, (string) $workflow->getInternalState()['counter'])->toRfc4122()];
    }

    public function getTaskResponse(WorkflowData $workflow, string $taskId, string $lang): array
    {
        return [];
    }

    public function ping(WorkflowData $workflow): ?WorkflowActionResult
    {
        return null;
    }
}
