<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Tests;

use Dbp\Relay\CoreBundle\Locale\Locale;
use Dbp\Relay\CoreBundle\TestUtils\DataProviderTester;
use Dbp\Relay\PortfolioBundle\ApiPlatform\WorkflowItem;
use Dbp\Relay\PortfolioBundle\ApiPlatform\WorkflowProvider;
use Dbp\Relay\PortfolioBundle\Authorization\AuthorizationService;
use Dbp\Relay\PortfolioBundle\Handler\Action;
use Dbp\Relay\PortfolioBundle\Handler\WorkflowTypeHandlerRegistry;
use Dbp\Relay\PortfolioBundle\Persistence\WorkflowPersistence;
use Dbp\Relay\PortfolioBundle\Service\WorkflowService;

class WorkflowProviderTest extends AbstractTestCase
{
    private DataProviderTester $tester;

    protected function setUp(): void
    {
        parent::setUp();

        $workflowService = $this->container->get(WorkflowService::class);
        $workflowTypeHandlerRegistry = $this->container->get(WorkflowTypeHandlerRegistry::class);
        $authorizationService = $this->container->get(AuthorizationService::class);
        $locale = $this->container->get(Locale::class);
        $provider = new WorkflowProvider($workflowService, $workflowTypeHandlerRegistry, $authorizationService, $locale);
        $this->tester = DataProviderTester::create($provider, WorkflowItem::class, ['PortfolioWorkflow:output']);
    }

    public function testGetItemNotFound(): void
    {
        $this->assertNull($this->tester->getItem('no-such-id'));
    }

    public function testGetItemBuildsDto(): void
    {
        $this->testEntityManager->addWorkflow('wf-1', DummyWorkflowTypeHandler::TYPE);

        /** @var WorkflowItem $item */
        $item = $this->tester->getItem('wf-1');
        $this->assertNotNull($item);
        $this->assertSame('wf-1', $item->getIdentifier());
        $this->assertSame(DummyWorkflowTypeHandler::TYPE, $item->getType());
        $this->assertSame(WorkflowPersistence::STATE_ACTIVE, $item->getState());
        $this->assertSame('Dummy Workflow', $item->getName());
        $this->assertSame('A test workflow', $item->getDescription());
        $this->assertSame(['label' => 'Pending', 'description' => 'Waiting for input'], $item->getCurrentStateDisplay());
        $this->assertSame([
            ['id' => DummyWorkflowTypeHandler::ACTION_PROCEED, 'label' => 'Proceed', 'type' => Action::TYPE_ACTION],
            ['id' => DummyWorkflowTypeHandler::ACTION_CANCEL, 'label' => 'Cancel', 'type' => Action::TYPE_ACTION],
        ], $item->getAvailableActions());
    }

    public function testGetCollectionEmpty(): void
    {
        $this->assertSame([], $this->tester->getCollection());
    }

    public function testGetCollectionReturnsList(): void
    {
        $this->testEntityManager->addWorkflow('wf-1', DummyWorkflowTypeHandler::TYPE);
        $this->testEntityManager->addWorkflow('wf-2', DummyWorkflowTypeHandler::TYPE);

        $items = $this->tester->getCollection();
        $this->assertCount(2, $items);
    }

    public function testGetCollectionItemsAreWorkflowItems(): void
    {
        $this->testEntityManager->addWorkflow('wf-1', DummyWorkflowTypeHandler::TYPE);

        $items = $this->tester->getCollection();
        $this->assertInstanceOf(WorkflowItem::class, $items[0]);
        $this->assertSame('wf-1', $items[0]->getIdentifier());
        $this->assertSame('Dummy Workflow', $items[0]->getName());
        $this->assertSame([
            ['id' => DummyWorkflowTypeHandler::ACTION_PROCEED, 'label' => 'Proceed', 'type' => Action::TYPE_ACTION],
            ['id' => DummyWorkflowTypeHandler::ACTION_CANCEL, 'label' => 'Cancel', 'type' => Action::TYPE_ACTION],
        ], $items[0]->getAvailableActions());
    }
}
