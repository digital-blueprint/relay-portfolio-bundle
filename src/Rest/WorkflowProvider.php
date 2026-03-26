<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Rest;

use Dbp\Relay\CoreBundle\Rest\AbstractDataProvider;
use Dbp\Relay\PortfolioBundle\Entity\Workflow;
use Dbp\Relay\PortfolioBundle\Service\WorkflowService;

/**
 * @extends AbstractDataProvider<Workflow>
 */
class WorkflowProvider extends AbstractDataProvider
{
    private WorkflowService $workflowService;

    public function __construct(WorkflowService $workflowService)
    {
        parent::__construct();
        $this->workflowService = $workflowService;
    }

    protected function getItemById(string $id, array $filters = [], array $options = []): ?Workflow
    {
        return $this->workflowService->getWorkflow($id, $filters, $options);
    }

    /**
     * @return Workflow[]
     */
    protected function getPage(int $currentPageNumber, int $maxNumItemsPerPage, array $filters = [], array $options = []): array
    {
        return $this->workflowService->getWorkflows($currentPageNumber, $maxNumItemsPerPage, $filters, $options);
    }
}
