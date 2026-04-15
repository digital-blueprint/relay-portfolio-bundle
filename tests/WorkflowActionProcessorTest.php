<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Tests;

use Dbp\Relay\CoreBundle\Locale\Locale;
use Dbp\Relay\CoreBundle\TestUtils\DataProcessorTester;
use Dbp\Relay\PortfolioBundle\ApiPlatform\WorkflowAction;
use Dbp\Relay\PortfolioBundle\ApiPlatform\WorkflowActionProcessor;
use Dbp\Relay\PortfolioBundle\ApiPlatform\WorkflowResultMessage;
use Dbp\Relay\PortfolioBundle\Authorization\AuthorizationService;
use Dbp\Relay\PortfolioBundle\Handler\WorkflowMessage;
use Dbp\Relay\PortfolioBundle\Persistence\WorkflowPersistence;
use Dbp\Relay\PortfolioBundle\Service\WorkflowService;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class WorkflowActionProcessorTest extends AbstractTestCase
{
    private DataProcessorTester $tester;

    protected function setUp(): void
    {
        parent::setUp();

        $workflowService = $this->container->get(WorkflowService::class);
        $authorizationService = $this->container->get(AuthorizationService::class);
        $locale = $this->container->get(Locale::class);
        $processor = new WorkflowActionProcessor($workflowService, $authorizationService, $locale);
        $this->tester = DataProcessorTester::create($processor, WorkflowAction::class);
    }

    private function makeAction(string $workflowId, string $action, array $payload = []): WorkflowAction
    {
        $wa = new WorkflowAction();
        $wa->setWorkflowId($workflowId);
        $wa->setAction($action);
        $wa->setPayload($payload);

        return $wa;
    }

    public function testAddItemMissingWorkflowId(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $wa = new WorkflowAction();
        $wa->setAction(DummyWorkflowTypeHandler::ACTION_PROCEED);
        $this->tester->addItem($wa);
    }

    public function testAddItemMissingAction(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $wa = new WorkflowAction();
        $wa->setWorkflowId('wf-1');
        $this->tester->addItem($wa);
    }

    public function testAddItemWorkflowNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->tester->addItem($this->makeAction('no-such-id', DummyWorkflowTypeHandler::ACTION_PROCEED));
    }

    public function testAddItemCanUseReturnsFalseGives404(): void
    {
        $this->testEntityManager->addWorkflow('wf-1', DummyWorkflowTypeHandler::TYPE);

        $handler = $this->container->get(DummyWorkflowTypeHandler::class);
        $handler->canUse = false;

        $this->expectException(NotFoundHttpException::class);
        $this->tester->addItem($this->makeAction('wf-1', DummyWorkflowTypeHandler::ACTION_PROCEED));
    }

    public function testAddItemUnavailableAction(): void
    {
        $this->testEntityManager->addWorkflow('wf-1', DummyWorkflowTypeHandler::TYPE);

        $this->expectException(BadRequestHttpException::class);
        $this->tester->addItem($this->makeAction('wf-1', 'unknown-action'));
    }

    public function testAddItemSuccess(): void
    {
        $this->testEntityManager->addWorkflow('wf-1', DummyWorkflowTypeHandler::TYPE);

        /** @var WorkflowAction $result */
        $result = $this->tester->addItem($this->makeAction('wf-1', DummyWorkflowTypeHandler::ACTION_PROCEED));

        $this->assertInstanceOf(WorkflowAction::class, $result);
        $this->assertNotNull($result->getIdentifier());
        $this->assertSame('message', $result->getType());
        $this->assertNull($result->getUrl());

        $msg = $result->getMessage();
        $this->assertInstanceOf(WorkflowResultMessage::class, $msg);
        $this->assertSame(WorkflowMessage::TYPE_INFO, $msg->getType());
        $this->assertSame('Step completed', $msg->getTitle());
        $this->assertSame('The workflow has been completed successfully.', $msg->getText());
    }

    public function testAddItemUpdatesWorkflowState(): void
    {
        $this->testEntityManager->addWorkflow('wf-1', DummyWorkflowTypeHandler::TYPE);
        $this->tester->addItem($this->makeAction('wf-1', DummyWorkflowTypeHandler::ACTION_PROCEED));

        $em = $this->testEntityManager->getEntityManager();
        $em->clear();
        $workflow = $em->find(WorkflowPersistence::class, 'wf-1');
        $this->assertSame(WorkflowPersistence::STATE_DONE, $workflow->getState());
    }
}
