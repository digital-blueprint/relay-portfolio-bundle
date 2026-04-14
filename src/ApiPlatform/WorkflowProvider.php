<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\ApiPlatform;

use Dbp\Relay\CoreBundle\Locale\Locale;
use Dbp\Relay\CoreBundle\Rest\AbstractDataProvider;
use Dbp\Relay\PortfolioBundle\Authorization\AuthorizationService;
use Dbp\Relay\PortfolioBundle\Handler\WorkflowData;
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
        private readonly Locale $locale,
    ) {
        parent::__construct();
    }

    protected function getItemById(string $id, array $filters = [], array $options = []): ?WorkflowItem
    {
        $this->authorizationService->checkCanUse();

        $workflow = $this->workflowService->getWorkflow($id);
        if ($workflow === null || !$this->workflowService->canUse($workflow)) {
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

        $type = $filters['type'] ?? null;
        $workflows = $this->workflowService->getWorkflows($currentPageNumber, $maxNumItemsPerPage, $type);

        return array_values(array_map(
            fn (WorkflowPersistence $w) => $this->toWorkflowItem($w, includeHandlerData: true),
            array_filter($workflows, fn (WorkflowPersistence $w) => $this->workflowService->canUse($w))
        ));
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
            $workflowData = $this->toWorkflowData($workflow);
            $lang = $this->locale->getCurrentPrimaryLanguage();

            $item->setName($handler->getName($workflowData, $lang));
            $item->setDescription($handler->getDescription($workflowData, $lang));

            $stateDisplay = $handler->getCurrentStateDisplay($workflowData, $lang);
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
                $handler->getAvailableActions($workflowData, $lang)
            ));
        }

        return $item;
    }

    private function toWorkflowData(WorkflowPersistence $workflow): WorkflowData
    {
        $createdAt = $workflow->getCreatedAt();
        if ($createdAt === null) {
            throw new \LogicException(sprintf("Workflow '%s' has no createdAt set.", $workflow->getId()));
        }

        return new WorkflowData(
            $workflow->getId(),
            $workflow->getType(),
            $workflow->getState(),
            $workflow->getInternalState(),
            $createdAt,
        );
    }
}
