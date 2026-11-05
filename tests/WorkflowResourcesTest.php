<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Tests;

use Dbp\Relay\PortfolioBundle\Handler\WorkflowData;
use Dbp\Relay\PortfolioBundle\Handler\WorkflowTypeHandlerRegistry;
use Dbp\Relay\PortfolioBundle\SignApi\SignServiceRegistry;
use Dbp\Relay\PortfolioBundle\Workflow\Dummy\DummyWorkflowTypeHandler;
use Dbp\Relay\PortfolioBundle\Workflow\Signing\SigningWorkflowTypeHandler;
use Dbp\Relay\PortfolioBundle\Workflow\Signing\SignService;
use Dbp\Relay\PortfolioBundle\Workflow\Signing\State\SigningExecutionState;
use Dbp\Relay\PortfolioBundle\Workflow\Signing\State\SigningIdentity;
use Dbp\Relay\PortfolioBundle\Workflow\Signing\State\SigningIteration;
use Dbp\Relay\PortfolioBundle\Workflow\Signing\State\SigningIterationKind;
use Dbp\Relay\PortfolioBundle\Workflow\Signing\State\SigningIterationState;
use Dbp\Relay\PortfolioBundle\Workflow\Signing\State\SigningJob;
use Dbp\Relay\PortfolioBundle\Workflow\Signing\State\SigningParticipant;
use Dbp\Relay\PortfolioBundle\Workflow\Signing\State\SigningTask;
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

    public function testSigningTitleAndDescriptionIdentifySigners(): void
    {
        $handler = $this->container->get(SigningWorkflowTypeHandler::class);
        $state = new SigningWorkflowState(
            new SigningJob('process49', new SigningIdentity('owner@example.com', 'owner-id'), ['fileId' => 'file-id', 'filename' => 'contract.pdf'], [
                new SigningIteration(0, SigningIterationKind::INTERNAL_APPROVAL, [new SigningParticipant('approver@example.com', userId: 'approver-id', displayName: 'Approver')]),
                new SigningIteration(1, SigningIterationKind::EXTERNAL_SIGNING, [new SigningParticipant('guest@example.com', displayName: 'Guest Example')]),
            ], description: 'Please sign the contract.'),
            new SigningExecutionState([
                new SigningIterationState(0, [new SigningTask(0, 'internal-task')]),
                new SigningIterationState(1, [new SigningTask(0, 'external-task')]),
            ]),
        );
        $workflow = new WorkflowData('wf-signing', $handler->getType(), true, $state->toArray(), new \DateTimeImmutable());

        self::assertStringContainsString('Guest Example', $handler->getName($workflow, 'de'));
        self::assertStringContainsString('guest@example.com', $handler->getName($workflow, 'de'));
        $description = $handler->getDescription($workflow, 'de');
        self::assertStringContainsString('Please sign the contract.', $description);
        self::assertStringContainsString('Workflow-ID: wf-signing.', $description);
        self::assertStringNotContainsString('Signaturfrist:', $description);
        self::assertStringNotContainsString('Referenz-ID:', $description);
        self::assertStringContainsString('Freigebende Person: Approver (approver@example.com).', $description);
        self::assertStringContainsString('Guest Example', $description);
        self::assertStringContainsString('guest@example.com', $description);

        $iterations = $state->job->toArray()['iterations'];
        foreach ($iterations as &$iteration) {
            $iteration['participants'][0]['displayName'] = null;
        }
        unset($iteration);
        $state = SigningTestUtils::withJob($state, ['iterations' => $iterations, 'expirationDate' => '2026-12-01T12:00:00+00:00', 'referenceId' => '00042']);
        $workflow = new WorkflowData('wf-signing', $handler->getType(), true, $state->toArray(), new \DateTimeImmutable());
        self::assertStringContainsString('guest@example.com', $handler->getName($workflow, 'de'));
        self::assertStringContainsString('guest@example.com', $handler->getDescription($workflow, 'de'));
        self::assertStringContainsString('Freigebende Person: approver@example.com.', $handler->getDescription($workflow, 'de'));
        self::assertStringContainsString('Signaturfrist: 2026-12-01 12:00:00 GMT+0000.', $handler->getDescription($workflow, 'de'));
        self::assertStringContainsString('Referenz-ID: 00042.', $handler->getDescription($workflow, 'de'));
    }
}
