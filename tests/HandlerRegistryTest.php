<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Tests;

use Dbp\Relay\PortfolioBundle\Handler\StatusDisplay;
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

    public function testStateDisplay(): void
    {
        $sd = new StatusDisplay('My Label', 'My Description');
        $this->assertSame('My Label', $sd->getLabel());
        $this->assertSame('My Description', $sd->getDescription());
    }

    public function testWorkflowActionResult(): void
    {
        $message = new WorkflowMessage(
            type: WorkflowMessage::TYPE_WARNING,
            title: 'Heads up',
            text: 'Something to note.',
        );

        $result = new WorkflowActionResult(
            internalState: ['key' => 'val'],
            close: true,
            message: $message,
        );

        $this->assertSame(['key' => 'val'], $result->getInternalState());
        $this->assertTrue($result->getClose());
        $this->assertSame($message, $result->getMessage());
        $this->assertNull($result->getUrl());
    }

    public function testWorkflowActionResultWithUrl(): void
    {
        $result = new WorkflowActionResult(
            internalState: ['key' => 'val'],
            url: 'https://example.com/signed?token=abc',
        );

        $this->assertSame('https://example.com/signed?token=abc', $result->getUrl());
        $this->assertNull($result->getMessage());
    }

    public function testWorkflowActionResultDefaults(): void
    {
        $result = new WorkflowActionResult(internalState: []);

        $this->assertNull($result->getClose());
        $this->assertNull($result->getMessage());
        $this->assertNull($result->getUrl());
    }

    public function testWorkflowActionResultMessageAndUrlThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new WorkflowActionResult(
            internalState: [],
            message: new WorkflowMessage(WorkflowMessage::TYPE_INFO, 'title', 'text'),
            url: 'https://example.com',
        );
    }

    public function testWorkflowMessage(): void
    {
        $msg = new WorkflowMessage(
            type: WorkflowMessage::TYPE_ERROR,
            title: 'Something went wrong',
            text: 'Please try again later.',
        );

        $this->assertSame(WorkflowMessage::TYPE_ERROR, $msg->getType());
        $this->assertSame('Something went wrong', $msg->getTitle());
        $this->assertSame('Please try again later.', $msg->getText());
    }
}
