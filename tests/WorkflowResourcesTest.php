<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Tests;

use Dbp\Relay\PortfolioBundle\Handler\WorkflowData;
use Dbp\Relay\PortfolioBundle\Handler\WorkflowTypeHandlerRegistry;
use Dbp\Relay\PortfolioBundle\SignApi\SignServiceRegistry;
use Dbp\Relay\PortfolioBundle\Workflow\Dummy\DummyWorkflowTypeHandler;
use Dbp\Relay\PortfolioBundle\Workflow\Signing\SigningWorkflowTypeHandler;
use Dbp\Relay\PortfolioBundle\Workflow\Signing\SignService;

class WorkflowResourcesTest extends AbstractTestCase
{
    public function testWorkflowHandlersAreRegistered(): void
    {
        $registry = $this->container->get(WorkflowTypeHandlerRegistry::class);

        $this->assertInstanceOf(DummyWorkflowTypeHandler::class, $registry->getHandler('dummy'));
        $this->assertInstanceOf(SigningWorkflowTypeHandler::class, $registry->getHandler('signing'));
    }

    public function testDummyWorkflowResources(): void
    {
        $handler = $this->container->get(DummyWorkflowTypeHandler::class);
        $workflow = new WorkflowData('wf-dummy', $handler->getType(), true, $handler->create([]), new \DateTimeImmutable());

        $this->assertSame('Untitled', $handler->getName($workflow, 'en'));
        $this->assertSame('Unbenannt', $handler->getName($workflow, 'de'));
        $this->assertSame('Waiting', $handler->getStatusDisplay($workflow, 'en')->getLabel());
        $this->assertSame('Wartend', $handler->getStatusDisplay($workflow, 'de')->getLabel());

        $html = $handler->getRenderResponse($workflow, DummyWorkflowTypeHandler::RENDER_HELLO, 'de')->getHtml();
        $this->assertStringContainsString('<html lang="de">', $html);
        $this->assertStringContainsString('<title>Unbenannt</title>', $html);
        $this->assertStringContainsString('Counter: 0', $html);
    }

    public function testSigningWorkflowTranslations(): void
    {
        $handler = $this->container->get(SigningWorkflowTypeHandler::class);
        $workflow = new WorkflowData('wf-signing', $handler->getType(), true, $handler->create([]), new \DateTimeImmutable());

        $this->assertSame('Signing Workflow', $handler->getName($workflow, 'en'));
        $this->assertSame('Signatur-Workflow', $handler->getName($workflow, 'de'));
        $this->assertSame('Pending', $handler->getStatusDisplay($workflow, 'en')->getLabel());
        $this->assertSame('Ausstehend', $handler->getStatusDisplay($workflow, 'de')->getLabel());
    }

    public function testDummySigningDocument(): void
    {
        $service = $this->container->get(SignServiceRegistry::class)->getService('process49');

        $this->assertInstanceOf(SignService::class, $service);
        $document = $service->getDocument('dummy-sign-test');
        $this->assertNotNull($document);
        $this->assertStringStartsWith('%PDF-', $document);
    }
}
