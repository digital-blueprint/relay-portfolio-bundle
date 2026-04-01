<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\ApiPlatform;

use Dbp\Relay\CoreBundle\Rest\AbstractDataProvider;
use Dbp\Relay\PortfolioBundle\Authorization\AuthorizationService;
use Dbp\Relay\PortfolioBundle\Handler\WorkflowTypeHandlerRegistry;
use Dbp\Relay\PortfolioBundle\Persistence\WorkflowPersistence;
use Dbp\Relay\PortfolioBundle\Service\WorkflowService;

/**
 * @extends AbstractDataProvider<WorkflowItem>
 */
class WorkflowProvider extends AbstractDataProvider
{
    public function __construct(
        private readonly WorkflowService $workflowService,
        private readonly WorkflowTypeHandlerRegistry $workflowTypeHandlerRegistry,
        private readonly AuthorizationService $authorizationService,
    ) {
        parent::__construct();
    }

    protected function getItemById(string $id, array $filters = [], array $options = []): ?WorkflowItem
    {
        $this->authorizationService->checkCanUse();

        $workflow = $this->workflowService->getWorkflow($id);
        if ($workflow === null) {
            return null;
        }

        return $this->toWorkflowItem($workflow, includeHandlerData: true);
    }

    /**
     * @return WorkflowItem[]
     */
    protected function getPage(int $currentPageNumber, int $maxNumItemsPerPage, array $filters = [], array $options = []): array
    {
        $this->authorizationService->checkCanUse();

        $workflows = $this->workflowService->getWorkflows($currentPageNumber, $maxNumItemsPerPage);

        return array_map(fn (WorkflowPersistence $w) => $this->toWorkflowItem($w, includeHandlerData: true), $workflows);
    }

    private function toWorkflowItem(WorkflowPersistence $workflow, bool $includeHandlerData): WorkflowItem
    {
        $item = new WorkflowItem();
        $item->setIdentifier($workflow->getId());
        $item->setType($workflow->getType());
        $item->setState($workflow->getState());
        $item->setCreatedAt($workflow->getCreatedAt());
        $item->setUpdatedAt($workflow->getUpdatedAt());

        if ($includeHandlerData && $this->workflowTypeHandlerRegistry->hasHandler($workflow->getType())) {
            $handler = $this->workflowTypeHandlerRegistry->getHandler($workflow->getType());
            $item->setName($handler->getName($workflow));
            $item->setDescription($handler->getDescription($workflow));

            $stateDisplay = $handler->getCurrentStateDisplay($workflow);
            $item->setCurrentStateDisplay([
                'label' => $stateDisplay->getLabel(),
                'description' => $stateDisplay->getDescription(),
            ]);

            $item->setAvailableActions(array_map(
                static function ($action): array {
                    $data = ['id' => $action->getId(), 'label' => $action->getLabel(), 'type' => $action->getType()];
                    if ($action->getUrl() !== null) {
                        $data['url'] = $action->getUrl();
                    }

                    return $data;
                },
                $handler->getAvailableActions($workflow)
            ));
        }

        return $item;
    }
}
