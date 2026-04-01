<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Tests;

use Dbp\Relay\CoreBundle\TestUtils\DataProviderTester;
use Dbp\Relay\PortfolioBundle\ApiPlatform\TaskItem;
use Dbp\Relay\PortfolioBundle\ApiPlatform\TaskProvider;
use Dbp\Relay\PortfolioBundle\Authorization\AuthorizationService;
use Dbp\Relay\PortfolioBundle\Service\WorkflowService;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class TaskProviderTest extends AbstractTestCase
{
    private DataProviderTester $tester;

    protected function setUp(): void
    {
        parent::setUp();

        $workflowService = $this->container->get(WorkflowService::class);
        $authorizationService = $this->container->get(AuthorizationService::class);
        $provider = new TaskProvider($workflowService, $authorizationService);
        $this->tester = DataProviderTester::create($provider, TaskItem::class, ['PortfolioTask:output']);
    }

    public function testGetItemNotFound(): void
    {
        $this->assertNull($this->tester->getItem('no-such-task'));
    }

    public function testGetItemBuildsDto(): void
    {
        $workflow = $this->testEntityManager->addWorkflow('wf-1', DummyWorkflowTypeHandler::TYPE);
        $this->testEntityManager->addTask('t-1', $workflow);

        /** @var TaskItem $item */
        $item = $this->tester->getItem('t-1');
        $this->assertNotNull($item);
        $this->assertSame('t-1', $item->getIdentifier());
        $this->assertSame('wf-1', $item->getWorkflowId());
        $this->assertSame(['info' => 'computed from wf-1'], $item->getData());
    }

    public function testGetCollectionRequiresWorkflowId(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->tester->getCollection();
    }

    public function testGetCollectionReturnsTasksForWorkflow(): void
    {
        $workflow = $this->testEntityManager->addWorkflow('wf-1', DummyWorkflowTypeHandler::TYPE);
        $this->testEntityManager->addTask('t-1', $workflow);
        $this->testEntityManager->addTask('t-2', $workflow);

        // A second workflow whose tasks must not appear in the results
        $other = $this->testEntityManager->addWorkflow('wf-2', DummyWorkflowTypeHandler::TYPE);
        $this->testEntityManager->addTask('t-other', $other);

        $items = $this->tester->getCollection(['workflowId' => 'wf-1']);
        $this->assertCount(2, $items);
    }
}
