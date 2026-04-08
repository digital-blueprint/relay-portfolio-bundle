<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Handler;

interface WorkflowTypeHandlerInterface
{
    /**
     * Returns the workflow type identifier this handler is responsible for.
     */
    public function getType(): string;

    /**
     * Returns the display name for the given workflow instance.
     */
    public function getName(WorkflowData $workflow): string;

    /**
     * Returns a human-readable description for the given workflow instance.
     */
    public function getDescription(WorkflowData $workflow): string;

    /**
     * Returns display information about the current state of the workflow.
     */
    public function getCurrentStateDisplay(WorkflowData $workflow): StateDisplay;

    /**
     * Returns whether the current user can view this workflow.
     *
     * Used to gate both single-item and collection access, as well as
     * actions and task endpoints. Return false to respond with 404
     * (preferred over 403 to avoid leaking existence).
     *
     * The current user is available via an injected UserSessionInterface.
     */
    public function canView(WorkflowData $workflow): bool;

    /**
     * Returns the actions available to the current user for this workflow.
     *
     * The current user is available via an injected UserSessionInterface in the implementing class.
     *
     * @return Action[]
     */
    public function getAvailableActions(WorkflowData $workflow): array;

    /**
     * Handles a workflow action triggered by the current user.
     *
     * Must be idempotent. The current user is available via an injected UserSessionInterface.
     *
     * @param array<string, mixed> $payload
     */
    public function handleAction(WorkflowData $workflow, string $action, array $payload): WorkflowActionResult;

    /**
     * Returns the complete set of task IDs that should currently exist for this workflow.
     *
     * Called after every state change (handleAction, ping) and on workflow creation.
     * The bundle diffs against the DB:
     * - present in result but not in DB → create
     * - present in DB but not in result → delete
     * - present in both → no-op
     *
     * Use stable, deterministic IDs (e.g. "sign-{workflowId}") so the diff is reliable.
     *
     * @return string[]
     */
    public function getExpectedTasks(WorkflowData $workflow): array;

    /**
     * Computes and returns the response data for a task belonging to this workflow.
     *
     * All task content is derived on-the-fly from the workflow's internal state.
     *
     * @return array<string, mixed>
     */
    public function getTaskResponse(WorkflowData $workflow, string $taskId): array;

    /**
     * Periodic check called by the cron job, e.g. for sending reminder emails or
     * advancing workflow state automatically (e.g. timeouts, external status checks).
     *
     * Called for every active workflow of this type. Must be safe to call repeatedly.
     * Return null if no state change is needed, or a WorkflowActionResult to update
     * the workflow state and/or create tasks.
     */
    public function ping(WorkflowData $workflow): ?WorkflowActionResult;
}
