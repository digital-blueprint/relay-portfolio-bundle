<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Tests;

use Dbp\Relay\PortfolioBundle\Handler\StateDisplay;
use Dbp\Relay\PortfolioBundle\Handler\WorkflowActionResult;
use Dbp\Relay\PortfolioBundle\Handler\WorkflowTypeHandlerRegistry;
use Dbp\Relay\PortfolioBundle\Persistence\WorkflowPersistence;
use PHPUnit\Framework\TestCase;

class HandlerRegistryTest extends TestCase
{
    // -------------------------------------------------------------------------
    // WorkflowTypeHandlerRegistry
    // -------------------------------------------------------------------------

    public function testWorkflowRegistryAddAndGet(): void
    {
        $registry = new WorkflowTypeHandlerRegistry();
        $handler = new DummyWorkflowTypeHandler();
        $registry->addHandler($handler);

        $this->assertSame($handler, $registry->getHandler(DummyWorkflowTypeHandler::TYPE));
    }

    public function testWorkflowRegistryHasHandler(): void
    {
        $registry = new WorkflowTypeHandlerRegistry();
        $this->assertFalse($registry->hasHandler('missing'));

        $registry->addHandler(new DummyWorkflowTypeHandler());
        $this->assertTrue($registry->hasHandler(DummyWorkflowTypeHandler::TYPE));
    }

    public function testWorkflowRegistryGetAllHandlers(): void
    {
        $registry = new WorkflowTypeHandlerRegistry();
        $this->assertCount(0, $registry->getAllHandlers());

        $registry->addHandler(new DummyWorkflowTypeHandler());
        $this->assertCount(1, $registry->getAllHandlers());
    }

    public function testWorkflowRegistryDuplicateThrows(): void
    {
        $this->expectException(\RuntimeException::class);

        $registry = new WorkflowTypeHandlerRegistry();
        $registry->addHandler(new DummyWorkflowTypeHandler());
        $registry->addHandler(new DummyWorkflowTypeHandler());
    }

    public function testWorkflowRegistryNotFoundThrows(): void
    {
        $this->expectException(\RuntimeException::class);

        (new WorkflowTypeHandlerRegistry())->getHandler('missing');
    }

    // -------------------------------------------------------------------------
    // Value objects
    // -------------------------------------------------------------------------

    public function testStateDisplay(): void
    {
        $sd = new StateDisplay('My Label', 'My Description');
        $this->assertSame('My Label', $sd->getLabel());
        $this->assertSame('My Description', $sd->getDescription());
    }

    public function testWorkflowActionResult(): void
    {
        $result = new WorkflowActionResult(
            customState: ['key' => 'val'],
            state: WorkflowPersistence::STATE_DONE,
            responseData: ['url' => '/next'],
        );

        $this->assertSame(['key' => 'val'], $result->getCustomState());
        $this->assertSame(WorkflowPersistence::STATE_DONE, $result->getState());
        $this->assertSame(['url' => '/next'], $result->getResponseData());
    }

    public function testWorkflowActionResultDefaults(): void
    {
        $result = new WorkflowActionResult(customState: []);

        $this->assertNull($result->getState());
        $this->assertSame([], $result->getResponseData());
    }
}
