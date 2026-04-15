<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\DummyWorkflow;

use Dbp\Relay\PortfolioBundle\Handler\Action;
use Dbp\Relay\PortfolioBundle\Handler\StateDisplay;
use Dbp\Relay\PortfolioBundle\Handler\WorkflowActionResult;
use Dbp\Relay\PortfolioBundle\Handler\WorkflowData;
use Dbp\Relay\PortfolioBundle\Handler\WorkflowMessage;
use Dbp\Relay\PortfolioBundle\Handler\WorkflowTypeHandlerHelper;
use Dbp\Relay\PortfolioBundle\Handler\WorkflowTypeHandlerInterface;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Uid\Uuid;

/**
 * Signing workflow — manages signing one or more PDF documents via an external service.
 *
 * Expected internalState shape at creation time:
 * {
 *   "documents": [
 *     { "url": "https://example.com/doc.pdf", "x": 100, "y": 200, "page": 1 },
 *     ...
 *   ]
 * }
 *
 * One task is created for the workflow while it is active. Its ID is a UUID v4 generated at
 * creation time and stored in internalState['taskId'].
 * The external signing service receives the task ID as a URL fragment and calls
 * GET /portfolio/tasks/{taskId} to retrieve the full document list. Each document has a
 * stable "id" of the form "{taskId}_{documentUuid}" so the external app can reference
 * individual documents unambiguously on callback.
 *
 * The "check" action inspects the signed flags and transitions to STATE_DONE when all
 * documents are marked signed. Flip "signed" flags directly in the DB to simulate callbacks
 * until a real trigger is implemented.
 */
class SigningWorkflowTypeHandler implements WorkflowTypeHandlerInterface
{
    public const TYPE = 'signing';
    public const SIGNING_SERVICE_URL = 'https://dbp-dev.tugraz.at/apps/esign';
    public const ACTION_SIGN = 'sign';
    public const ACTION_CHECK = 'check';

    private readonly Translator $translator;

    public function __construct(
        private readonly WorkflowTypeHandlerHelper $helper,
    ) {
        $translator = new Translator('en');
        $translator->addLoader('yaml', new YamlFileLoader());
        $translator->addResource('yaml', __DIR__.'/translations/messages.en.yaml', 'en');
        $translator->addResource('yaml', __DIR__.'/translations/messages.de.yaml', 'de');
        $this->translator = $translator;
    }

    public function create(array $input): array
    {
        $taskId = Uuid::v4()->toRfc4122();
        $documents = $input['documents'] ?? [];

        foreach ($documents as &$doc) {
            $doc['id'] = Uuid::v4()->toRfc4122();
            $doc['signed'] = false;
        }
        unset($doc);

        return [
            'taskId' => $taskId,
            'documents' => $documents,
        ];
    }

    public function getType(): string
    {
        return self::TYPE;
    }

    public function getName(WorkflowData $workflow, string $lang): string
    {
        return $this->translator->trans('signing_workflow.name', locale: $lang);
    }

    public function getDescription(WorkflowData $workflow, string $lang): string
    {
        return $this->translator->trans('signing_workflow.description', locale: $lang);
    }

    public function getCurrentStateDisplay(WorkflowData $workflow, string $lang): StateDisplay
    {
        $documents = $workflow->getInternalState()['documents'] ?? [];
        $total = count($documents);
        $signed = count(array_filter($documents, fn (array $doc) => $doc['signed'] ?? false));

        return match ($workflow->getState()) {
            WorkflowData::STATE_DONE => new StateDisplay(
                $this->translator->trans('signing_workflow.state.completed', locale: $lang),
                $this->translator->trans('signing_workflow.state.completed_desc', ['%total%' => $total], locale: $lang),
            ),
            default => new StateDisplay(
                $this->translator->trans('signing_workflow.state.pending', locale: $lang),
                $this->translator->trans('signing_workflow.state.pending_desc', ['%signed%' => $signed, '%total%' => $total], locale: $lang),
            ),
        };
    }

    public function canUse(WorkflowData $workflow): bool
    {
        return true;
    }

    public function getAvailableActions(WorkflowData $workflow, string $lang): array
    {
        if ($workflow->getState() !== WorkflowData::STATE_ACTIVE) {
            return [];
        }

        $taskId = $this->getTaskId($workflow);
        $signedUrl = $this->helper->getSignedTaskUrl($taskId);
        $fragment = rawurlencode($signedUrl);
        $path = '/'.rawurlencode($lang).'/predefined-signature';

        return [
            new Action(
                self::ACTION_SIGN,
                $this->translator->trans('signing_workflow.action.sign', locale: $lang),
                Action::TYPE_URL,
                self::SIGNING_SERVICE_URL.$path.'#'.$fragment,
            ),
            new Action(
                self::ACTION_SIGN,
                $this->translator->trans('signing_workflow.action.task', locale: $lang),
                Action::TYPE_URL,
                $signedUrl,
            ),
            new Action(
                self::ACTION_CHECK,
                $this->translator->trans('signing_workflow.action.check', locale: $lang),
            ),
        ];
    }

    public function handleAction(WorkflowData $workflow, string $action, array $payload, string $lang): WorkflowActionResult
    {
        $internalState = $workflow->getInternalState();
        $documents = $internalState['documents'] ?? [];
        $total = count($documents);
        $signed = count(array_filter($documents, fn (array $doc) => $doc['signed'] ?? false));

        if ($signed === $total && $total > 0) {
            return new WorkflowActionResult(
                internalState: $internalState,
                state: WorkflowData::STATE_DONE,
                message: new WorkflowMessage(
                    type: WorkflowMessage::TYPE_SUCCESS,
                    title: $this->translator->trans('signing_workflow.message.completed_title', locale: $lang),
                    text: $this->translator->trans('signing_workflow.message.completed_text', ['%total%' => $total], locale: $lang),
                ),
            );
        }

        return new WorkflowActionResult(
            internalState: $internalState,
            message: new WorkflowMessage(
                type: WorkflowMessage::TYPE_INFO,
                title: $this->translator->trans('signing_workflow.message.pending_title', locale: $lang),
                text: $this->translator->trans('signing_workflow.message.pending_text', ['%signed%' => $signed, '%total%' => $total], locale: $lang),
            ),
        );
    }

    public function getExpectedTasks(WorkflowData $workflow): array
    {
        if ($workflow->getState() === WorkflowData::STATE_ACTIVE) {
            return [$this->getTaskId($workflow)];
        }

        return [];
    }

    public function getTaskResponse(WorkflowData $workflow, string $taskId, string $lang): array
    {
        if ($taskId !== $this->getTaskId($workflow)) {
            throw new \RuntimeException(sprintf("Task '%s' does not belong to workflow '%s'.", $taskId, $workflow->getId()));
        }

        $documents = $workflow->getInternalState()['documents'] ?? [];

        $result = [];
        foreach ($documents as $doc) {
            if ($doc['signed']) {
                continue;
            }
            $result[] = [
                'workflowTrackingId' => $taskId.'_'.$doc['id'],
                'url' => $doc['url'],
                'x' => $doc['x'],
                'y' => $doc['y'],
                'page' => $doc['page'],
                'profile' => $doc['profile'],
            ];
        }

        return ['documents' => $result];
    }

    public function ping(WorkflowData $workflow): ?WorkflowActionResult
    {
        return null;
    }

    private function getTaskId(WorkflowData $workflow): string
    {
        return $workflow->getInternalState()['taskId'];
    }
}
