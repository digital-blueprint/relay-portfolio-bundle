<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Handler;

use Dbp\Relay\BlobLibrary\Api\BlobFile;
use Dbp\Relay\BlobLibrary\Api\BlobFileStream;
use Dbp\Relay\PortfolioBundle\Service\WorkflowFileService;

/**
 * File operations scoped to a single workflow, including before persistence.
 */
final readonly class WorkflowFiles
{
    public function __construct(
        private WorkflowFileService $fileService,
        private string $workflowId,
    ) {
    }

    public function store(string $filename, string $mimeType, string $contents): string
    {
        return $this->fileService->store($this->workflowId, $filename, $mimeType, $contents);
    }

    public function get(string $fileId): BlobFile
    {
        return $this->fileService->get($this->workflowId, $fileId);
    }

    public function download(string $fileId): BlobFileStream
    {
        return $this->fileService->download($this->workflowId, $fileId);
    }

    public function createDownloadUrl(string $fileId, ?int $ttlSeconds = null): string
    {
        return $this->fileService->createDownloadUrl($this->workflowId, $fileId, $ttlSeconds);
    }

    /**
     * @return iterable<BlobFile>
     */
    public function list(): iterable
    {
        return $this->fileService->list($this->workflowId);
    }

    public function delete(string $fileId): void
    {
        $this->fileService->delete($this->workflowId, $fileId);
    }

    public function deleteAll(): void
    {
        $this->fileService->deleteAll($this->workflowId);
    }
}
