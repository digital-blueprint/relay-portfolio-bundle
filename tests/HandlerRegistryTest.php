<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Tests;

use Dbp\Relay\PortfolioBundle\Handler\WorkflowActionResult;
use Dbp\Relay\PortfolioBundle\Handler\WorkflowMessage;
use Dbp\Relay\PortfolioBundle\Handler\WorkflowTypeHandlerRegistry;
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

    public function testWorkflowActionResultMessageAndUrlThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new WorkflowActionResult(
            internalState: [],
            message: new WorkflowMessage(WorkflowMessage::TYPE_INFO, 'title', 'text'),
            url: 'https://example.com',
        );
    }
}
