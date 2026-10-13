<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Tests;

use Dbp\Relay\PortfolioBundle\Handler\WorkflowTypeHandlerHelper;
use Dbp\Relay\PortfolioBundle\Service\WorkflowFileService;
use Symfony\Component\Uid\Uuid;

class WorkflowFileServiceTest extends AbstractTestCase
{
    private const WORKFLOW_ID = 'cf8c9e2e-22d7-4fb2-b9b3-1d6f6f650864';
    private WorkflowFileService $files;

    protected function setUp(): void
    {
        parent::setUp();
        $this->files = $this->container->get(WorkflowFileService::class);
    }

    public function testStoreAndDownload(): void
    {
        $id = $this->files->store(strtoupper(self::WORKFLOW_ID), 'contract.txt', 'text/plain', 'Contract');
        $file = $this->files->get(self::WORKFLOW_ID, $id);
        $this->assertSame('portfolio/workflows/'.self::WORKFLOW_ID, $file->getPrefix());
        $this->assertSame('contract.txt', $file->getFileName());
        $this->assertSame('text/plain', $file->getMimeType());
        $this->assertSame('Contract', $this->files->download(self::WORKFLOW_ID, $id)->getFileStream()->getContents());
        $this->assertCount(1, iterator_to_array($this->files->list(self::WORKFLOW_ID)));
    }

    public function testHelperProvidesWorkflowScopedFiles(): void
    {
        $helper = $this->container->get(WorkflowTypeHandlerHelper::class);
        $files = $helper->getFiles(self::WORKFLOW_ID);
        $otherFiles = $helper->getFiles(Uuid::v4()->toRfc4122());
        $id = $files->store('contract.txt', 'text/plain', 'Contract');
        $otherFiles->store('other.txt', 'text/plain', 'Other');

        $this->assertSame('portfolio/workflows/'.self::WORKFLOW_ID, $files->get($id)->getPrefix());
        $this->assertSame('Contract', $files->download($id)->getFileStream()->getContents());
        $this->assertCount(1, iterator_to_array($files->list()));
        $files->delete($id);
        $files->deleteAll();
        $this->assertSame([], iterator_to_array($files->list()));
        $this->assertCount(1, iterator_to_array($otherFiles->list()));
    }

    public function testInvalidWorkflowId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->files->get('../other', 'file-id');
    }

    public function testGetRejectsFilesFromAnotherWorkflow(): void
    {
        $id = $this->files->store(Uuid::v4()->toRfc4122(), 'other.txt', 'text/plain', 'Other');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('does not belong');
        $this->files->get(self::WORKFLOW_ID, $id);
    }

    public function testDownloadUrlRejectsNonPositiveLifetime(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->files->createDownloadUrl(self::WORKFLOW_ID, 'file-id', 0);
    }

    public function testDeleteIsIdempotent(): void
    {
        $id = $this->files->store(self::WORKFLOW_ID, 'contract.txt', 'text/plain', 'Contract');
        $this->files->delete(self::WORKFLOW_ID, $id);
        $this->files->delete(self::WORKFLOW_ID, $id);
        $this->assertSame([], iterator_to_array($this->files->list(self::WORKFLOW_ID)));
    }

    public function testDeleteAllAcrossPages(): void
    {
        $otherWorkflowId = Uuid::v4()->toRfc4122();
        $otherFile = $this->files->store($otherWorkflowId, 'other.txt', 'text/plain', 'Other');
        foreach (range(1, 101) as $index) {
            $this->files->store(self::WORKFLOW_ID, $index.'.txt', 'text/plain', 'Contract');
        }
        $this->assertCount(101, iterator_to_array($this->files->list(self::WORKFLOW_ID)));

        $this->files->deleteAll(self::WORKFLOW_ID);
        $this->files->deleteAll(self::WORKFLOW_ID);
        $this->assertSame([], iterator_to_array($this->files->list(self::WORKFLOW_ID)));
        $this->assertSame('Other', $this->files->download($otherWorkflowId, $otherFile)->getFileStream()->getContents());
    }
}
