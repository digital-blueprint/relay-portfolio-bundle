<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Service;

use Dbp\Relay\PortfolioBundle\Handler\WorkflowActionResult;
use Dbp\Relay\PortfolioBundle\Handler\WorkflowData;
use Dbp\Relay\PortfolioBundle\Handler\WorkflowTypeHandlerRegistry;
use Dbp\Relay\PortfolioBundle\Persistence\TaskPersistence;
use Dbp\Relay\PortfolioBundle\Persistence\WorkflowPersistence;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

class WorkflowService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly WorkflowTypeHandlerRegistry $workflowTypeHandlerRegistry,
    ) {
    }

    // -------------------------------------------------------------------------
    // Workflows
    // -------------------------------------------------------------------------

    /**
     * Creates a new workflow instance, persists it, and reconciles its initial tasks.
     *
     * @param array<string, mixed> $internalState
     */
    public function createWorkflow(string $type, array $internalState): WorkflowPersistence
    {
        $this->workflowTypeHandlerRegistry->getHandler($type);

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $workflow = new WorkflowPersistence();
        $workflow->setId(Uuid::v4()->toRfc4122());
        $workflow->setType($type);
        $workflow->setState(WorkflowPersistence::STATE_ACTIVE);
        $workflow->setInternalState($internalState);
        $workflow->setCreatedAt($now);
        $workflow->setUpdatedAt($now);

        $this->em->wrapInTransaction(function () use ($workflow): void {
            $this->em->persist($workflow);
            $this->em->flush();
            $this->reconcileTasks($workflow);
        });

        return $workflow;
    }

    /**
     * Returns the workflow if it exists and the current user can view it, null otherwise.
     */
    public function getWorkflow(string $id): ?WorkflowPersistence
    {
        $workflow = $this->em->getRepository(WorkflowPersistence::class)->find($id);
        if ($workflow === null || !$this->canView($workflow)) {
            return null;
        }

        return $workflow;
    }

    /**
     * @return WorkflowPersistence[]
     */
    public function getWorkflows(int $currentPageNumber, int $maxNumItemsPerPage, ?string $type = null): array
    {
        $criteria = $type !== null ? ['type' => $type] : [];
        $workflows = $this->em->getRepository(WorkflowPersistence::class)
            ->findBy($criteria, ['createdAt' => 'DESC'], $maxNumItemsPerPage, ($currentPageNumber - 1) * $maxNumItemsPerPage);

        return array_values(array_filter($workflows, fn ($w) => $this->canView($w)));
    }

    /**
     * Triggers a workflow action, persists the state change, and reconciles tasks — all in one transaction.
     *
     * @param array<string, mixed> $payload
     *
     * @throws NotFoundHttpException   if the workflow does not exist or is not visible to the current user
     * @throws BadRequestHttpException if the action is not available
     */
    public function handleAction(string $workflowId, string $action, array $payload, string $lang): WorkflowActionResult
    {
        $workflow = $this->em->getRepository(WorkflowPersistence::class)->find($workflowId);
        if ($workflow === null || !$this->canView($workflow)) {
            throw new NotFoundHttpException(sprintf("Workflow '%s' not found.", $workflowId));
        }

        $handler = $this->workflowTypeHandlerRegistry->getHandler($workflow->getType());
        $workflowData = $this->toWorkflowData($workflow);

        $availableActions = $handler->getAvailableActions($workflowData, $lang);
        $availableActionIds = array_map(fn ($a) => $a->getId(), $availableActions);
        if (!in_array($action, $availableActionIds, true)) {
            throw new BadRequestHttpException(sprintf(
                "Action '%s' is not available for workflow '%s'. Available: %s",
                $action,
                $workflowId,
                implode(', ', $availableActionIds)
            ));
        }

        $result = $handler->handleAction($workflowData, $action, $payload, $lang);

        $this->em->wrapInTransaction(function () use ($workflow, $result): void {
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

            $workflow->setInternalState($result->getInternalState());
            if ($result->getState() !== null) {
                $workflow->setState($result->getState());
            }
            $workflow->setUpdatedAt($now);
            $this->em->flush();

            $this->reconcileTasks($workflow);
        });

        return $result;
    }

    // -------------------------------------------------------------------------
    // Tasks
    // -------------------------------------------------------------------------

    /**
     * Returns the task if it exists and the current user can view its workflow, null otherwise.
     */
    public function getTask(string $id): ?TaskPersistence
    {
        $task = $this->em->getRepository(TaskPersistence::class)->find($id);
        if ($task === null) {
            return null;
        }

        $workflow = $task->getWorkflow();
        if ($workflow === null || !$this->canView($workflow)) {
            return null;
        }

        return $task;
    }

    /**
     * Lists tasks for a given workflow.
     *
     * @return TaskPersistence[]
     *
     * @throws NotFoundHttpException if the workflow does not exist or is not visible to the current user
     */
    public function getTasksForWorkflow(string $workflowId, int $currentPageNumber, int $maxNumItemsPerPage): array
    {
        $workflow = $this->em->getRepository(WorkflowPersistence::class)->find($workflowId);
        if ($workflow === null || !$this->canView($workflow)) {
            throw new NotFoundHttpException(sprintf("Workflow '%s' not found.", $workflowId));
        }

        return $this->em->getRepository(TaskPersistence::class)->findBy(
            ['workflow' => $workflow],
            ['createdAt' => 'DESC'],
            $maxNumItemsPerPage,
            ($currentPageNumber - 1) * $maxNumItemsPerPage
        );
    }

    /**
     * Returns the computed task response data for a given task.
     *
     * @return array<string, mixed>
     *
     * @throws NotFoundHttpException if the task or its workflow does not exist or is not visible
     */
    public function getTaskResponse(TaskPersistence $task, string $lang): array
    {
        $workflow = $task->getWorkflow();
        if ($workflow === null || !$this->canView($workflow)) {
            throw new NotFoundHttpException(sprintf("Workflow for task '%s' not found.", $task->getId()));
        }

        $handler = $this->workflowTypeHandlerRegistry->getHandler($workflow->getType());

        return $handler->getTaskResponse($this->toWorkflowData($workflow), $task->getId(), $lang);
    }

    // -------------------------------------------------------------------------
    // Ping (called by cron + console command — no auth check)
    // -------------------------------------------------------------------------

    /**
     * Calls ping() on the appropriate handler for every active workflow,
     * and applies any returned state changes + task reconciliation in a transaction.
     */
    public function pingAll(): void
    {
        $workflows = $this->em->getRepository(WorkflowPersistence::class)->findBy([
            'state' => WorkflowPersistence::STATE_ACTIVE,
        ]);

        foreach ($workflows as $workflow) {
            if (!$this->workflowTypeHandlerRegistry->hasHandler($workflow->getType())) {
                continue;
            }
            $handler = $this->workflowTypeHandlerRegistry->getHandler($workflow->getType());
            $result = $handler->ping($this->toWorkflowData($workflow));

            $this->em->wrapInTransaction(function () use ($workflow, $result): void {
                if ($result !== null) {
                    $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
                    $workflow->setInternalState($result->getInternalState());
                    if ($result->getState() !== null) {
                        $workflow->setState($result->getState());
                    }
                    $workflow->setUpdatedAt($now);
                    $this->em->flush();
                }

                $this->reconcileTasks($workflow);
            });
        }
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    private function canView(WorkflowPersistence $workflow): bool
    {
        if (!$this->workflowTypeHandlerRegistry->hasHandler($workflow->getType())) {
            return false;
        }

        return $this->workflowTypeHandlerRegistry->getHandler($workflow->getType())->canView($this->toWorkflowData($workflow));
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

    /**
     * Diffs the handler's expected tasks against the DB and creates/deletes accordingly.
     * Must be called within an active transaction.
     */
    private function reconcileTasks(WorkflowPersistence $workflow): void
    {
        if (!$this->workflowTypeHandlerRegistry->hasHandler($workflow->getType())) {
            return;
        }

        $handler = $this->workflowTypeHandlerRegistry->getHandler($workflow->getType());
        $expectedIds = $handler->getExpectedTasks($this->toWorkflowData($workflow));

        $existingTasks = $this->em->getRepository(TaskPersistence::class)->findBy(['workflow' => $workflow]);
        $existingIds = array_map(fn (TaskPersistence $t) => $t->getId(), $existingTasks);

        $toCreate = array_diff($expectedIds, $existingIds);
        $toDelete = array_diff($existingIds, $expectedIds);

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        foreach ($toCreate as $taskId) {
            $task = new TaskPersistence();
            $task->setId($taskId);
            $task->setWorkflow($workflow);
            $task->setCreatedAt($now);
            $this->em->persist($task);
        }

        foreach ($toDelete as $taskId) {
            $task = $this->em->getRepository(TaskPersistence::class)->find($taskId);
            if ($task !== null) {
                $this->em->remove($task);
            }
        }

        $this->em->flush();
    }
}
