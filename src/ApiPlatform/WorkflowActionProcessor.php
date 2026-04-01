<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\ApiPlatform;

use Dbp\Relay\CoreBundle\Rest\AbstractDataProcessor;
use Dbp\Relay\PortfolioBundle\Authorization\AuthorizationService;
use Dbp\Relay\PortfolioBundle\Service\WorkflowService;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Uid\Uuid;

class WorkflowActionProcessor extends AbstractDataProcessor
{
    public function __construct(
        private readonly WorkflowService $workflowService,
        private readonly AuthorizationService $authorizationService,
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

        $result = $this->workflowService->handleAction($workflowId, $action, $data->getPayload());

        $data->setIdentifier(Uuid::v4()->toRfc4122());
        $data->setResponseData($result->getResponseData());

        return $data;
    }
}
