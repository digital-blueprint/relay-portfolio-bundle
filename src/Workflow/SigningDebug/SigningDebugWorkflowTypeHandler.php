<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Workflow\SigningDebug;

use Dbp\Relay\BasePersonBundle\API\PersonProviderInterface;
use Dbp\Relay\CoreBundle\Rest\Options;
use Dbp\Relay\PortfolioBundle\Handler\Action;
use Dbp\Relay\PortfolioBundle\Handler\CleanupResult;
use Dbp\Relay\PortfolioBundle\Handler\RenderResult;
use Dbp\Relay\PortfolioBundle\Handler\StatusDisplay;
use Dbp\Relay\PortfolioBundle\Handler\WorkflowActionResult;
use Dbp\Relay\PortfolioBundle\Handler\WorkflowData;
use Dbp\Relay\PortfolioBundle\Handler\WorkflowMessage;
use Dbp\Relay\PortfolioBundle\Handler\WorkflowTypeHandlerInterface;
use Dbp\Relay\PortfolioBundle\SignApi\SignException;
use Dbp\Relay\PortfolioBundle\SignApi\SignJobDescription;
use Dbp\Relay\PortfolioBundle\Workflow\Signing\SignService;
use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;

class SigningDebugWorkflowTypeHandler implements WorkflowTypeHandlerInterface
{
    public const TYPE = 'signing-debug';
    public const ACTION_CREATE = 'create-signing-workflow';

    private readonly Translator $translator;

    /** @param \Closure(): SignService $signService */
    public function __construct(
        private readonly PersonProviderInterface $persons,
        // Defer resolution because the signing service depends on the handler registry.
        #[AutowireServiceClosure(SignService::class)]
        private readonly \Closure $signService,
    ) {
        $translator = new Translator('en');
        $translator->addLoader('yaml', new YamlFileLoader());
        $translator->addResource('yaml', __DIR__.'/Resources/translations/messages.en.yaml', 'en');
        $translator->addResource('yaml', __DIR__.'/Resources/translations/messages.de.yaml', 'de');
        $this->translator = $translator;
    }

    public function create(string $workflowId, array $input): array
    {
        return [];
    }

    public function getType(): string
    {
        return self::TYPE;
    }

    public function getName(WorkflowData $workflow, string $lang): string
    {
        return $this->translator->trans('signing_debug.name', locale: $lang);
    }

    public function getDescription(WorkflowData $workflow, string $lang): string
    {
        return $this->translator->trans('signing_debug.description', locale: $lang);
    }

    public function getStatusDisplay(WorkflowData $workflow, string $lang): StatusDisplay
    {
        return new StatusDisplay(
            $this->translator->trans('signing_debug.state.ready', locale: $lang),
            $this->translator->trans('signing_debug.state.ready_description', locale: $lang),
        );
    }

    public function canUse(WorkflowData $workflow): bool
    {
        return true;
    }

    public function getAvailableActions(WorkflowData $workflow, string $lang): array
    {
        if (!$workflow->isActive() || !$this->canUse($workflow)) {
            return [];
        }

        return [new Action(self::ACTION_CREATE, $this->translator->trans('signing_debug.action.create', locale: $lang))];
    }

    public function handleAction(WorkflowData $workflow, string $action, array $payload, string $lang): WorkflowActionResult
    {
        if (!$this->canUse($workflow) || !$workflow->isActive()) {
            throw new NotFoundHttpException('Signing debug workflow not found.');
        }
        if ($action !== self::ACTION_CREATE) {
            throw new BadRequestHttpException('Unknown signing debug action.');
        }
        $person = $this->persons->getCurrentPerson([Options::LOCAL_DATA_ATTRIBUTES => ['email']]);
        $email = $person?->getLocalDataValue('email');
        if (!is_string($email) || filter_var(trim($email), FILTER_VALIDATE_EMAIL) === false) {
            throw new SignException('The current user must have a valid email address to create a signing debug workflow.');
        }
        $user = ['@class' => 'com.example.api.User', 'classifier' => 'EMAIL', 'name' => trim($email)];
        $job = SignJobDescription::fromArray([
            'constituent' => $user,
            'positionType' => 'SIGNATURE_PAGE',
            'iterationData' => [
                ['iterationNumber' => 0, 'category' => 'APPROVAL', 'invitees' => [$user]],
                ['iterationNumber' => 1, 'category' => 'EXTERNAL_APPROVAL', 'invitees' => [[
                    ...$user,
                    '@class' => 'com.example.api.ExternalUser',
                    'externalUserName' => trim(($person->getGivenName() ?? '').' '.($person->getFamilyName() ?? '')) ?: trim($email),
                    'locale' => $lang,
                ]]],
            ],
        ]);
        $id = ($this->signService)()->startProcess(
            SignService::PROCESS_ID,
            $job,
            $this->getSamplePdf('debug-document.pdf'),
            [$this->getSamplePdf('debug-attachment.pdf')],
        );

        return new WorkflowActionResult(internalState: [], message: new WorkflowMessage(
            type: WorkflowMessage::TYPE_SUCCESS,
            title: $this->translator->trans('signing_debug.message.created', locale: $lang),
            text: $this->translator->trans('signing_debug.message.created_description', ['%id%' => $id], locale: $lang),
        ));
    }

    private function getSamplePdf(string $filename): UploadedFile
    {
        // Test mode permits the bundled fixture instead of requiring an HTTP upload.
        return new UploadedFile(__DIR__.'/../Signing/Resources/signed-document.pdf', $filename, 'application/pdf', null, true);
    }

    public function getExpectedTasks(WorkflowData $workflow): array
    {
        return [];
    }

    public function getTaskResponse(WorkflowData $workflow, string $taskId, string $lang): array
    {
        throw new NotFoundHttpException('Signing debug workflows have no tasks.');
    }

    public function getRenderResponse(WorkflowData $workflow, string $renderId, string $lang): RenderResult
    {
        throw new NotFoundHttpException('Signing debug workflows have no render content.');
    }

    public function ping(WorkflowData $workflow): ?WorkflowActionResult
    {
        return null;
    }

    public function cleanup(WorkflowData $workflow): CleanupResult
    {
        return new CleanupResult(done: true, internalState: []);
    }
}
