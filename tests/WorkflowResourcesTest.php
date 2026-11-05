<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Tests;

use Dbp\Relay\PortfolioBundle\Handler\WorkflowData;
use Dbp\Relay\PortfolioBundle\Handler\WorkflowTypeHandlerRegistry;
use Dbp\Relay\PortfolioBundle\SignApi\SignCategory;
use Dbp\Relay\PortfolioBundle\SignApi\SignServiceRegistry;
use Dbp\Relay\PortfolioBundle\Workflow\Dummy\DummyWorkflowTypeHandler;
use Dbp\Relay\PortfolioBundle\Workflow\Signing\SigningWorkflowTypeHandler;
use Dbp\Relay\PortfolioBundle\Workflow\Signing\SignService;
use Dbp\Relay\PortfolioBundle\Workflow\Signing\State\SigningInvitee;
use Dbp\Relay\PortfolioBundle\Workflow\Signing\State\SigningIteration;
use Dbp\Relay\PortfolioBundle\Workflow\Signing\State\SigningWorkflowState;

class WorkflowResourcesTest extends AbstractTestCase
{
    public function testWorkflowHandlersAreRegistered(): void
    {
        $registry = $this->container->get(WorkflowTypeHandlerRegistry::class);

        $this->assertInstanceOf(DummyWorkflowTypeHandler::class, $registry->getHandler('dummy'));
        $this->assertInstanceOf(SigningWorkflowTypeHandler::class, $registry->getHandler('signing'));
    }

    public function testSigningServiceIsRegistered(): void
    {
        $service = $this->container->get(SignServiceRegistry::class)->getService('process49');

        $this->assertInstanceOf(SignService::class, $service);
        $this->assertNull($service->resolveProcessId('dummy-sign-test'));
    }

    public function testSigningTitleAndDescriptionIdentifyExternalSigner(): void
    {
        $handler = $this->container->get(SigningWorkflowTypeHandler::class);
        $external = new SigningInvitee('external-task', 'guest@example.com', displayName: 'Guest Example');
        $state = new SigningWorkflowState('process49', 'owner@example.com', 'owner-id', 'file-id', 'contract.pdf', [
            new SigningIteration(0, SignCategory::APPROVAL, [new SigningInvitee('internal-task', 'approver@example.com', userId: 'approver-id', displayName: 'Approver')]),
            new SigningIteration(1, SignCategory::EXTERNAL_APPROVAL, [$external]),
        ], description: 'Please sign the contract.');
        $workflow = new WorkflowData('wf-signing', $handler->getType(), true, $state->toArray(), new \DateTimeImmutable());

        self::assertStringContainsString('Guest Example', $handler->getName($workflow, 'de'));
        self::assertStringContainsString('guest@example.com', $handler->getName($workflow, 'de'));
        $description = $handler->getDescription($workflow, 'de');
        self::assertStringContainsString('Please sign the contract.', $description);
        self::assertStringContainsString('Guest Example', $description);
        self::assertStringContainsString('guest@example.com', $description);

        $external->displayName = null;
        $workflow = new WorkflowData('wf-signing', $handler->getType(), true, $state->toArray(), new \DateTimeImmutable());
        self::assertStringContainsString('guest@example.com', $handler->getName($workflow, 'de'));
        self::assertStringContainsString('guest@example.com', $handler->getDescription($workflow, 'de'));
    }
}
