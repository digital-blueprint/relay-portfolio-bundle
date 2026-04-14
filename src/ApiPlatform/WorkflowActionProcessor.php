<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\ApiPlatform;

use Dbp\Relay\CoreBundle\Locale\Locale;
use Dbp\Relay\CoreBundle\Rest\AbstractDataProcessor;
use Dbp\Relay\PortfolioBundle\Authorization\AuthorizationService;
use Dbp\Relay\PortfolioBundle\Service\WorkflowService;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

class WorkflowActionProcessor extends AbstractDataProcessor
{
    public function __construct(
        private readonly WorkflowService $workflowService,
        private readonly AuthorizationService $authorizationService,
        private readonly Locale $locale,
    ) {
        parent::__construct();
    }

    protected function addItem(mixed $data, array $filters): WorkflowAction
    {
        $this->authorizationService->checkCanUse();

        assert($data instanceof WorkflowAction);

        $workflowId = $data->getWorkflowId();
        $action = $data->getAction();

        if ($workflowId === null) {
            throw new BadRequestHttpException('workflowId is required.');
        }
        if ($action === null) {
            throw new BadRequestHttpException('action is required.');
        }

        $workflow = $this->workflowService->getWorkflow($workflowId);
        if ($workflow === null || !$this->workflowService->canUse($workflow)) {
            throw new NotFoundHttpException(sprintf("Workflow '%s' not found.", $workflowId));
        }

        $result = $this->workflowService->handleAction($workflowId, $action, $data->getPayload(), $this->locale->getCurrentPrimaryLanguage());

        $data->setIdentifier(Uuid::v4()->toRfc4122());
        $message = $result->getMessage();
        $data->setMessage($message !== null ? WorkflowResultMessage::fromMessage($message) : null);

        return $data;
    }
}
