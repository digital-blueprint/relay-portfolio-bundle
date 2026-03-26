<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Service;

use Dbp\Relay\PortfolioBundle\Entity\Workflow;

class WorkflowService
{
    public function setConfig(array $config): void
    {
    }

    public function getWorkflow(string $identifier, array $filters = [], array $options = []): ?Workflow
    {
        return null;
    }

    /**
     * @return Workflow[]
     */
    public function getWorkflows(int $currentPageNumber, int $maxNumItemsPerPage, array $filters, array $options): array
    {
        return [];
    }

    public function addWorkflow(Workflow $data): Workflow
    {
        return $data;
    }

    public function removeWorkflow(Workflow $data): void
    {
    }
}
