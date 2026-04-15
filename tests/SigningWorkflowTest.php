<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Tests;

use Dbp\Relay\PortfolioBundle\DummyWorkflow\SigningWorkflowTypeHandler;
use Dbp\Relay\PortfolioBundle\Handler\Action;
use Dbp\Relay\PortfolioBundle\Handler\WorkflowData;
use Dbp\Relay\PortfolioBundle\Persistence\WorkflowPersistence;
use Dbp\Relay\PortfolioBundle\Service\WorkflowService;

class SigningWorkflowTest extends AbstractTestCase
{
    private WorkflowService $service;
    private SigningWorkflowTypeHandler $handler;

    /**
     * Caller-supplied input — no taskId, no signed flag; those are set by create().
     *
     * @var array<int, array{url: string, x: int, y: int, page: int}>
     */
    private array $twoDocumentInput = [
        ['url' => 'https://example.com/doc1.pdf', 'x' => 100, 'y' => 200, 'page' => 1, 'profile' => 'official'],
        ['url' => 'https://example.com/doc2.pdf', 'x' => 50, 'y' => 300, 'page' => 2, 'profile' => 'official'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->container->get(WorkflowService::class);
        $this->handler = $this->container->get(SigningWorkflowTypeHandler::class);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function toWorkflowData(WorkflowPersistence $workflow): WorkflowData
    {
        return new WorkflowData(
            $workflow->getId(),
            $workflow->getType(),
            $workflow->getState(),
            $workflow->getInternalState(),
            $workflow->getCreatedAt() ?? new \DateTimeImmutable(),
        );
    }

    /**
     * Builds a raw internalState as create() would produce it, for use with
     * addWorkflow() in tests that need to bypass create() (e.g. to pre-set signed flags).
     *
     * @param array<int, array{url: string, x: int, y: int, page: int, signed: bool}> $documents
     *
     * @return array<string, mixed>
     */
    private function buildInternalState(string $taskId, array $documents): array
    {
        return [
            'taskId' => $taskId,
            'documents' => $documents,
        ];
    }

    // -------------------------------------------------------------------------
    // create() — unit tests on the handler directly
    // -------------------------------------------------------------------------

    public function testCreateGeneratesTaskId(): void
    {
        $state = $this->handler->create(['documents' => $this->twoDocumentInput]);

        $this->assertArrayHasKey('taskId', $state);
        $this->assertNotEmpty($state['taskId']);
    }

    public function testCreateSetsSignedFalse(): void
    {
        $state = $this->handler->create(['documents' => $this->twoDocumentInput]);

        foreach ($state['documents'] as $doc) {
            $this->assertFalse($doc['signed']);
        }
    }

    public function testCreateGeneratesPerDocumentIds(): void
    {
        $state = $this->handler->create(['documents' => $this->twoDocumentInput]);

        foreach ($state['documents'] as $doc) {
            // Stored id is a bare UUID; the taskId prefix is added on-the-fly in getTaskResponse()
            $this->assertArrayHasKey('id', $doc);
            $this->assertMatchesRegularExpression('/^[0-9a-f\-]{36}$/', $doc['id']);
        }

        // Each document gets a unique id
        $ids = array_column($state['documents'], 'id');
        $this->assertSame(count($ids), count(array_unique($ids)));
    }

    public function testCreatePreservesDocumentFields(): void
    {
        $state = $this->handler->create(['documents' => $this->twoDocumentInput]);
        $docs = $state['documents'];

        $this->assertSame('https://example.com/doc1.pdf', $docs[0]['url']);
        $this->assertSame(100, $docs[0]['x']);
        $this->assertSame(200, $docs[0]['y']);
        $this->assertSame(1, $docs[0]['page']);
    }

    public function testCreateProducesUniqueTaskIds(): void
    {
        $state1 = $this->handler->create(['documents' => $this->twoDocumentInput]);
        $state2 = $this->handler->create(['documents' => $this->twoDocumentInput]);

        $this->assertNotSame($state1['taskId'], $state2['taskId']);
    }

    // -------------------------------------------------------------------------
    // Workflow creation via WorkflowService
    // -------------------------------------------------------------------------

    public function testCreateWorkflowIsActive(): void
    {
        $workflow = $this->service->createWorkflow(
            SigningWorkflowTypeHandler::TYPE,
            ['documents' => $this->twoDocumentInput],
        );

        $this->assertSame(WorkflowPersistence::STATE_ACTIVE, $workflow->getState());
        $this->assertSame(SigningWorkflowTypeHandler::TYPE, $workflow->getType());
    }

    public function testCreateWorkflowStoresTaskIdInInternalState(): void
    {
        $workflow = $this->service->createWorkflow(
            SigningWorkflowTypeHandler::TYPE,
            ['documents' => $this->twoDocumentInput],
        );

        $this->assertArrayHasKey('taskId', $workflow->getInternalState());
    }

    public function testCreateWorkflowCreatesSingleTask(): void
    {
        $workflow = $this->service->createWorkflow(
            SigningWorkflowTypeHandler::TYPE,
            ['documents' => $this->twoDocumentInput],
        );

        $taskId = $workflow->getInternalState()['taskId'];
        $tasks = $this->service->getTasksForWorkflow($workflow->getId(), 1, 10);

        $this->assertCount(1, $tasks);
        $this->assertSame($taskId, $tasks[0]->getId());
    }

    // -------------------------------------------------------------------------
    // Task response
    // -------------------------------------------------------------------------

    public function testTaskResponseContainsAllDocuments(): void
    {
        $workflow = $this->service->createWorkflow(
            SigningWorkflowTypeHandler::TYPE,
            ['documents' => $this->twoDocumentInput],
        );

        $taskId = $workflow->getInternalState()['taskId'];
        $task = $this->service->getTask($taskId);
        $this->assertNotNull($task);

        $data = $this->service->getTaskResponse($task, 'en');

        $this->assertArrayHasKey('documents', $data);
        $this->assertCount(2, $data['documents']);
    }

    public function testTaskResponseDocumentFields(): void
    {
        $workflow = $this->service->createWorkflow(
            SigningWorkflowTypeHandler::TYPE,
            ['documents' => $this->twoDocumentInput],
        );

        $taskId = $workflow->getInternalState()['taskId'];
        $task = $this->service->getTask($taskId);
        $this->assertNotNull($task);
        $docs = $this->service->getTaskResponse($task, 'en')['documents'];

        $taskId = $workflow->getInternalState()['taskId'];

        $this->assertStringStartsWith($taskId.'_', $docs[0]['workflowTrackingId']);
        $this->assertSame('https://example.com/doc1.pdf', $docs[0]['url']);
        $this->assertSame(100, $docs[0]['x']);
        $this->assertSame(200, $docs[0]['y']);
        $this->assertSame(1, $docs[0]['page']);
        $this->assertArrayNotHasKey('signed', $docs[0]);

        $this->assertStringStartsWith($taskId.'_', $docs[1]['workflowTrackingId']);
        $this->assertSame('https://example.com/doc2.pdf', $docs[1]['url']);
        $this->assertSame(50, $docs[1]['x']);
        $this->assertSame(300, $docs[1]['y']);
        $this->assertSame(2, $docs[1]['page']);
        $this->assertArrayNotHasKey('signed', $docs[1]);
    }

    public function testTaskResponseExcludesSignedDocuments(): void
    {
        $workflow = $this->testEntityManager->addWorkflow(
            'wf-partial',
            SigningWorkflowTypeHandler::TYPE,
            WorkflowPersistence::STATE_ACTIVE,
            $this->buildInternalState('task-uuid-partial', [
                ['id' => 'doc-uuid-1', 'url' => 'https://example.com/doc1.pdf', 'x' => 100, 'y' => 200, 'page' => 1, 'signed' => true, 'profile' => 'official'],
                ['id' => 'doc-uuid-2', 'url' => 'https://example.com/doc2.pdf', 'x' => 50, 'y' => 300, 'page' => 2, 'signed' => false, 'profile' => 'official'],
            ]),
        );
        $this->testEntityManager->addTask('task-uuid-partial', $workflow);

        $task = $this->service->getTask('task-uuid-partial');
        $this->assertNotNull($task);
        $docs = $this->service->getTaskResponse($task, 'en')['documents'];

        // Only the unsigned document is returned
        $this->assertCount(1, $docs);
        $this->assertSame('task-uuid-partial_doc-uuid-2', $docs[0]['workflowTrackingId']);
        $this->assertArrayNotHasKey('signed', $docs[0]);
    }

    // -------------------------------------------------------------------------
    // Sign action
    // -------------------------------------------------------------------------

    public function testSignActionIsTypeAction(): void
    {
        $workflow = $this->service->createWorkflow(
            SigningWorkflowTypeHandler::TYPE,
            ['documents' => $this->twoDocumentInput],
        );

        $actions = $this->handler->getAvailableActions($this->toWorkflowData($workflow), 'en');

        $signAction = null;
        foreach ($actions as $action) {
            if ($action->getId() === SigningWorkflowTypeHandler::ACTION_SIGN) {
                $signAction = $action;
                break;
            }
        }

        $this->assertNotNull($signAction);
        $this->assertSame(Action::TYPE_ACTION, $signAction->getType());
    }

    public function testSignActionReturnsUrlContainingTaskId(): void
    {
        $workflow = $this->service->createWorkflow(
            SigningWorkflowTypeHandler::TYPE,
            ['documents' => $this->twoDocumentInput],
        );

        $taskId = $workflow->getInternalState()['taskId'];
        $result = $this->handler->handleAction($this->toWorkflowData($workflow), SigningWorkflowTypeHandler::ACTION_SIGN, [], 'en');

        $this->assertNull($result->getMessage());
        $this->assertNotNull($result->getUrl());
        $this->assertStringStartsWith(SigningWorkflowTypeHandler::SIGNING_SERVICE_URL, $result->getUrl());
        $this->assertStringContainsString($taskId, $result->getUrl());
    }

    public function testCheckActionIsAvailableWhileActive(): void
    {
        $workflow = $this->service->createWorkflow(
            SigningWorkflowTypeHandler::TYPE,
            ['documents' => $this->twoDocumentInput],
        );

        $actions = $this->handler->getAvailableActions($this->toWorkflowData($workflow), 'en');
        $ids = array_map(fn (Action $a) => $a->getId(), $actions);

        $this->assertContains(SigningWorkflowTypeHandler::ACTION_CHECK, $ids);
    }

    public function testNoActionsWhenDone(): void
    {
        $workflow = $this->testEntityManager->addWorkflow(
            'wf-done',
            SigningWorkflowTypeHandler::TYPE,
            WorkflowPersistence::STATE_DONE,
            $this->buildInternalState('task-uuid-done', [
                ['id' => 'doc-uuid-1', 'url' => 'https://example.com/doc1.pdf', 'x' => 100, 'y' => 200, 'page' => 1, 'signed' => true],
            ]),
        );

        $this->assertSame([], $this->handler->getAvailableActions($this->toWorkflowData($workflow), 'en'));
    }

    // -------------------------------------------------------------------------
    // Check action — none signed
    // -------------------------------------------------------------------------

    public function testCheckWhenNoneSigned(): void
    {
        $workflow = $this->service->createWorkflow(
            SigningWorkflowTypeHandler::TYPE,
            ['documents' => $this->twoDocumentInput],
        );

        $result = $this->service->handleAction(
            $workflow->getId(),
            SigningWorkflowTypeHandler::ACTION_CHECK,
            [],
            'en',
        );

        // null state = no transition; workflow stays active
        $this->assertNull($result->getState());
        $this->assertNotNull($result->getMessage());
        $this->assertStringContainsString('0 / 2', $result->getMessage()->getText());
    }

    // -------------------------------------------------------------------------
    // Check action — all signed → transitions to DONE
    // -------------------------------------------------------------------------

    public function testCheckWhenAllSignedTransitionsToDone(): void
    {
        // Use addWorkflow() to inject a state where both docs are already signed
        $workflow = $this->testEntityManager->addWorkflow(
            'wf-signing',
            SigningWorkflowTypeHandler::TYPE,
            WorkflowPersistence::STATE_ACTIVE,
            $this->buildInternalState('task-uuid-1', [
                ['id' => 'doc-uuid-1', 'url' => 'https://example.com/doc1.pdf', 'x' => 100, 'y' => 200, 'page' => 1, 'signed' => true],
                ['id' => 'doc-uuid-2', 'url' => 'https://example.com/doc2.pdf', 'x' => 50, 'y' => 300, 'page' => 2, 'signed' => true],
            ]),
        );
        // Add the task that getExpectedTasks() expects to already exist
        $this->testEntityManager->addTask('task-uuid-1', $workflow);

        $result = $this->service->handleAction(
            $workflow->getId(),
            SigningWorkflowTypeHandler::ACTION_CHECK,
            [],
            'en',
        );

        $this->assertSame(WorkflowPersistence::STATE_DONE, $result->getState());
        $this->assertNotNull($result->getMessage());

        $persisted = $this->service->getWorkflow($workflow->getId());
        $this->assertNotNull($persisted);
        $this->assertSame(WorkflowPersistence::STATE_DONE, $persisted->getState());
    }

    public function testTaskDeletedAfterTransitionToDone(): void
    {
        $workflow = $this->testEntityManager->addWorkflow(
            'wf-signing-2',
            SigningWorkflowTypeHandler::TYPE,
            WorkflowPersistence::STATE_ACTIVE,
            $this->buildInternalState('task-uuid-2', [
                ['id' => 'doc-uuid-1', 'url' => 'https://example.com/doc1.pdf', 'x' => 100, 'y' => 200, 'page' => 1, 'signed' => true],
            ]),
        );
        $this->testEntityManager->addTask('task-uuid-2', $workflow);

        $tasksBefore = $this->service->getTasksForWorkflow($workflow->getId(), 1, 10);
        $this->assertCount(1, $tasksBefore);

        $this->service->handleAction(
            $workflow->getId(),
            SigningWorkflowTypeHandler::ACTION_CHECK,
            [],
            'en',
        );

        // getExpectedTasks() returns [] for done state → task removed by reconciliation
        $tasksAfter = $this->service->getTasksForWorkflow($workflow->getId(), 1, 10);
        $this->assertCount(0, $tasksAfter);
    }

    // -------------------------------------------------------------------------
    // State display
    // -------------------------------------------------------------------------

    public function testStateDisplayWhileActive(): void
    {
        $workflow = $this->service->createWorkflow(
            SigningWorkflowTypeHandler::TYPE,
            ['documents' => $this->twoDocumentInput],
        );

        $display = $this->handler->getCurrentStateDisplay($this->toWorkflowData($workflow), 'en');

        $this->assertSame('Pending', $display->getLabel());
        $this->assertStringContainsString('0 / 2', $display->getDescription());
    }

    public function testStateDisplayWhenDone(): void
    {
        $workflow = $this->testEntityManager->addWorkflow(
            'wf-done-display',
            SigningWorkflowTypeHandler::TYPE,
            WorkflowPersistence::STATE_DONE,
            $this->buildInternalState('task-uuid-done-display', [
                ['id' => 'doc-uuid-1', 'url' => 'https://example.com/doc1.pdf', 'x' => 100, 'y' => 200, 'page' => 1, 'signed' => true],
                ['id' => 'doc-uuid-2', 'url' => 'https://example.com/doc2.pdf', 'x' => 50, 'y' => 300, 'page' => 2, 'signed' => true],
            ]),
        );

        $display = $this->handler->getCurrentStateDisplay($this->toWorkflowData($workflow), 'en');

        $this->assertSame('Completed', $display->getLabel());
        $this->assertStringContainsString('2', $display->getDescription());
    }
}
