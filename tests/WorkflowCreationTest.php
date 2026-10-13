<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Tests;

use Dbp\Relay\PortfolioBundle\Handler\WorkflowTypeHandlerInterface;
use Dbp\Relay\PortfolioBundle\Handler\WorkflowTypeHandlerRegistry;
use Dbp\Relay\PortfolioBundle\Persistence\WorkflowPersistence;
use Dbp\Relay\PortfolioBundle\Service\WorkflowFileService;
use Dbp\Relay\PortfolioBundle\Service\WorkflowService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Uid\Uuid;

class WorkflowCreationTest extends AbstractTestCase
{
    private WorkflowFileService $files;

    protected function setUp(): void
    {
        parent::setUp();
        $this->files = $this->container->get(WorkflowFileService::class);
    }

    private function service(WorkflowTypeHandlerInterface $handler): WorkflowService
    {
        $registry = new WorkflowTypeHandlerRegistry();
        $registry->addHandler($handler);

        return new WorkflowService($this->entityManager(), $registry, $this->files);
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->container->get('doctrine.orm.dbp_relay_portfolio_bundle_entity_manager');
    }

    public function testFilesAreInitializedBeforeWorkflowIsPersisted(): void
    {
        $handler = $this->createMock(WorkflowTypeHandlerInterface::class);
        $handler->method('getType')->willReturn('creation-test');
        $handler->expects($this->once())->method('create')->willReturnCallback(
            function (string $id, array $input): array {
                $this->assertTrue(Uuid::isValid($id));
                $this->assertNull($this->entityManager()->find(WorkflowPersistence::class, $id));

                return ['workflowId' => $id, 'fileId' => $this->files->store($id, 'contract.txt', 'text/plain', $input['contents'])];
            });
        $handler->method('getExpectedTasks')->willReturn([]);

        $workflow = $this->service($handler)->createWorkflow('creation-test', ['contents' => 'Contract']);
        $this->assertSame($workflow->getId(), $workflow->getInternalState()['workflowId']);
        $this->assertSame('Contract', $this->files->download($workflow->getId(), $workflow->getInternalState()['fileId'])->getFileStream()->getContents());
        $this->assertSame($workflow, $this->entityManager()->find(WorkflowPersistence::class, $workflow->getId()));
    }

    public static function failureStages(): iterable
    {
        yield 'initialization' => ['create'];
        yield 'task reconciliation' => ['getExpectedTasks'];
    }

    #[DataProvider('failureStages')]
    public function testFailedCreationCleansFilesAndLeavesNoWorkflow(string $stage): void
    {
        $failure = new \RuntimeException('Creation failed');
        $workflowId = null;
        $handler = $this->createMock(WorkflowTypeHandlerInterface::class);
        $handler->method('getType')->willReturn('creation-test');
        $handler->method('create')->willReturnCallback(
            function (string $id) use (&$workflowId, $failure, $stage): array {
                $workflowId = $id;
                $this->files->store($id, 'first.txt', 'text/plain', 'First file');
                $this->files->store($id, 'second.txt', 'text/plain', 'Second file');
                if ($stage === 'create') {
                    throw $failure;
                }

                return [];
            });
        if ($stage === 'getExpectedTasks') {
            $handler->method('getExpectedTasks')->willThrowException($failure);
        }
        try {
            $this->service($handler)->createWorkflow('creation-test', []);
            $this->fail('Expected creation to fail.');
        } catch (\RuntimeException $error) {
            $this->assertSame($failure, $error);
        }
        $connection = $this->entityManager()->getConnection();
        $this->assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM portfolio_workflows'));
        $this->assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM portfolio_tasks'));
        $this->assertSame([], iterator_to_array($this->files->list($workflowId)));
    }
}
