<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\ApiPlatform;

use Dbp\Relay\CoreBundle\Locale\Locale;
use Dbp\Relay\CoreBundle\Rest\AbstractDataProvider;
use Dbp\Relay\PortfolioBundle\Authorization\AuthorizationService;
use Dbp\Relay\PortfolioBundle\Persistence\TaskPersistence;
use Dbp\Relay\PortfolioBundle\Service\WorkflowService;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @extends AbstractDataProvider<TaskItem>
 */
class TaskProvider extends AbstractDataProvider
{
    public function __construct(
        private readonly WorkflowService $workflowService,
        private readonly AuthorizationService $authorizationService,
        private readonly Locale $locale,
    ) {
        parent::__construct();
    }

    protected function getItemById(string $id, array $filters = [], array $options = []): ?TaskItem
    {
        $this->authorizationService->checkCanUse();

        $task = $this->workflowService->getTask($id);
        if ($task === null) {
            return null;
        }

        $taskData = $this->workflowService->getTaskResponse($task, $this->locale->getCurrentPrimaryLanguage());

        return $this->toTaskItem($task, $taskData);
    }

    /**
     * @return TaskItem[]
     */
    protected function getPage(int $currentPageNumber, int $maxNumItemsPerPage, array $filters = [], array $options = []): array
    {
        $this->authorizationService->checkCanUse();

        $workflowId = $filters['workflowId'] ?? null;
        if ($workflowId === null || $workflowId === '') {
            throw new BadRequestHttpException('The workflowId filter is required.');
        }

        $tasks = $this->workflowService->getTasksForWorkflow($workflowId, $currentPageNumber, $maxNumItemsPerPage);

        return array_map(fn (TaskPersistence $t) => $this->toTaskItem($t, []), $tasks);
    }

    /**
     * @param array<string, mixed> $taskData
     */
    private function toTaskItem(TaskPersistence $task, array $taskData): TaskItem
    {
        $item = new TaskItem();
        $item->setIdentifier($task->getId());
        $item->setCreatedAt($task->getCreatedAt());
        $item->setWorkflowId($task->getWorkflow()?->getId());
        $item->setData($taskData);

        return $item;
    }
}
