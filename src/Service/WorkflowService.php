<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Service;

use Dbp\Relay\PortfolioBundle\Handler\WorkflowActionResult;
use Dbp\Relay\PortfolioBundle\Handler\WorkflowData;
use Dbp\Relay\PortfolioBundle\Handler\WorkflowTypeHandlerRegistry;
use Dbp\Relay\PortfolioBundle\Persistence\TaskPersistence;
use Dbp\Relay\PortfolioBundle\Persistence\WorkflowPersistence;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

class WorkflowService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly WorkflowTypeHandlerRegistry $workflowTypeHandlerRegistry,
    ) {
        $this->logger = new NullLogger();
    }

    // -------------------------------------------------------------------------
    // Workflows
    // -------------------------------------------------------------------------

    /**
     * Creates a new workflow instance, persists it, and reconciles its initial tasks.
     *
     * The handler's create() method is called first to transform the caller-supplied
     * input into the actual internalState. This allows handlers to generate stable IDs,
     * set defaults, and validate input before anything is persisted.
     *
     * @param array<string, mixed> $input
     */
    public function createWorkflow(string $type, array $input): WorkflowPersistence
    {
        $handler = $this->workflowTypeHandlerRegistry->getHandler($type);
        $internalState = $handler->create($input);

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
     * Soft-deletes a workflow by setting its deletedAt timestamp.
     *
     * The workflow is immediately hidden from the API. The cleanup cron job will
     * call handler->cleanup() periodically until it returns that it is done, at which point
     * the workflow is hard-deleted from the database.
     *
     * Returns true if the workflow was found and soft-deleted, false if not found
     * or already soft-deleted.
     */
    public function softDeleteWorkflow(string $id): bool
    {
        $workflow = $this->em->getRepository(WorkflowPersistence::class)->find($id);
        if ($workflow === null || $workflow->isDeleted()) {
            return false;
        }

        $workflow->setDeletedAt(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        $this->em->flush();

        return true;
    }

    /**
     * Iterates all soft-deleted workflows and calls cleanup() on their handler.
     * If cleanup() returns true, the workflow is hard-deleted from the database.
     */
    public function cleanupAll(): void
    {
        $qb = $this->em->getRepository(WorkflowPersistence::class)->createQueryBuilder('w');
        $workflows = $qb->where($qb->expr()->isNotNull('w.deletedAt'))->getQuery()->getResult();

        foreach ($workflows as $workflow) {
            if (!$this->workflowTypeHandlerRegistry->hasHandler($workflow->getType())) {
                $this->logger->warning('Portfolio cleanup: no handler registered for workflow type, skipping.', [
                    'workflowId' => $workflow->getId(),
                    'type' => $workflow->getType(),
                ]);
                continue;
            }

            $handler = $this->workflowTypeHandlerRegistry->getHandler($workflow->getType());
            $result = $handler->cleanup($this->toWorkflowData($workflow));

            if ($result->isDone()) {
                $this->hardDeleteWorkflow($workflow);
            } else {
                $this->em->wrapInTransaction(function () use ($workflow, $result): void {
                    $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
                    $workflow->setInternalState($result->getInternalState());
                    $workflow->setUpdatedAt($now);
                    $this->em->flush();
                });
            }
        }
    }

    /**
     * Returns the workflow if it exists and has not been soft-deleted, null otherwise.
     */
    public function getWorkflow(string $id): ?WorkflowPersistence
    {
        $workflow = $this->em->getRepository(WorkflowPersistence::class)->find($id);
        if ($workflow === null || $workflow->isDeleted()) {
            return null;
        }

        return $workflow;
    }

    /**
     * @return WorkflowPersistence[]
     */
    public function getWorkflows(int $currentPageNumber, int $maxNumItemsPerPage, ?string $type = null): array
    {
        $qb = $this->em->getRepository(WorkflowPersistence::class)->createQueryBuilder('w');
        $qb->where($qb->expr()->isNull('w.deletedAt'))
            ->orderBy('w.createdAt', 'DESC')
            ->setMaxResults($maxNumItemsPerPage)
            ->setFirstResult(($currentPageNumber - 1) * $maxNumItemsPerPage);

        if ($type !== null) {
            $qb->andWhere('w.type = :type')->setParameter('type', $type);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Triggers a workflow action, persists the state change, and reconciles tasks — all in one transaction.
     *
     * @param array<string, mixed> $payload
     *
     * @throws NotFoundHttpException   if the workflow does not exist
     * @throws BadRequestHttpException if the action is not available
     */
    public function handleAction(string $workflowId, string $action, array $payload, string $lang): WorkflowActionResult
    {
        $workflow = $this->em->getRepository(WorkflowPersistence::class)->find($workflowId);
        if ($workflow === null) {
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
     * Returns the task if it exists and its workflow has not been soft-deleted, null otherwise.
     */
    public function getTask(string $id): ?TaskPersistence
    {
        $task = $this->em->getRepository(TaskPersistence::class)->find($id);
        if ($task === null) {
            return null;
        }

        $workflow = $task->getWorkflow();
        if ($workflow === null || $workflow->isDeleted()) {
            return null;
        }

        return $task;
    }

    /**
     * Lists tasks for a given workflow.
     *
     * @return TaskPersistence[]
     *
     * @throws NotFoundHttpException if the workflow does not exist
     */
    public function getTasksForWorkflow(string $workflowId, int $currentPageNumber, int $maxNumItemsPerPage): array
    {
        $workflow = $this->em->getRepository(WorkflowPersistence::class)->find($workflowId);
        if ($workflow === null) {
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
     * @throws NotFoundHttpException if the task's workflow does not exist
     */
    public function getTaskResponse(TaskPersistence $task, string $lang): array
    {
        $workflow = $task->getWorkflow();
        if ($workflow === null) {
            throw new NotFoundHttpException(sprintf("Workflow for task '%s' not found.", $task->getId()));
        }

        $handler = $this->workflowTypeHandlerRegistry->getHandler($workflow->getType());

        return $handler->getTaskResponse($this->toWorkflowData($workflow), $task->getId(), $lang);
    }

    // -------------------------------------------------------------------------
    // Ping (called by cron + console command — no auth check)
    // -------------------------------------------------------------------------

    /**
     * Calls ping() on the appropriate handler for every active, non-deleted workflow,
     * and applies any returned state changes + task reconciliation in a transaction.
     */
    public function pingAll(): void
    {
        $qb = $this->em->getRepository(WorkflowPersistence::class)->createQueryBuilder('w');
        $workflows = $qb
            ->where('w.state = :state')
            ->andWhere($qb->expr()->isNull('w.deletedAt'))
            ->setParameter('state', WorkflowPersistence::STATE_ACTIVE)
            ->getQuery()
            ->getResult();

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

    public function canUse(WorkflowPersistence $workflow): bool
    {
        if (!$this->workflowTypeHandlerRegistry->hasHandler($workflow->getType())) {
            return false;
        }

        return $this->workflowTypeHandlerRegistry->getHandler($workflow->getType())
            ->canUse($this->toWorkflowData($workflow));
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
            $workflow->getDeletedAt(),
        );
    }

    /**
     * Removes a workflow and all its tasks from the database immediately.
     * Must only be called after external cleanup is complete (i.e. deletedAt is set).
     */
    private function hardDeleteWorkflow(WorkflowPersistence $workflow): void
    {
        if (!$workflow->isDeleted()) {
            throw new \LogicException(sprintf(
                "hardDeleteWorkflow() called on workflow '%s' that has not been soft-deleted first.",
                $workflow->getId()
            ));
        }

        $this->em->wrapInTransaction(function () use ($workflow): void {
            $tasks = $this->em->getRepository(TaskPersistence::class)->findBy(['workflow' => $workflow]);
            foreach ($tasks as $task) {
                $this->em->remove($task);
            }
            $this->em->remove($workflow);
            $this->em->flush();
        });
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
