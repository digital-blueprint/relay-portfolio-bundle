<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Rest;

use Dbp\Relay\CoreBundle\Rest\AbstractDataProcessor;
use Dbp\Relay\PortfolioBundle\Entity\Workflow;
use Dbp\Relay\PortfolioBundle\Service\WorkflowService;

class WorkflowProcessor extends AbstractDataProcessor
{
    private WorkflowService $workflowService;

    public function __construct(WorkflowService $workflowService)
    {
        parent::__construct();
        $this->workflowService = $workflowService;
    }

    protected function addItem(mixed $data, array $filters): Workflow
    {
        assert($data instanceof Workflow);

        $data->setIdentifier('42');

        return $this->workflowService->addWorkflow($data);
    }

    protected function removeItem($identifier, $data, array $filters): void
    {
        assert($data instanceof Workflow);

        $this->workflowService->removeWorkflow($data);
    }
}
