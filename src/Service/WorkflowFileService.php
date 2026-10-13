<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Service;

use Dbp\Relay\BlobLibrary\Api\BlobApi;
use Dbp\Relay\BlobLibrary\Api\BlobApiError;
use Dbp\Relay\BlobLibrary\Api\BlobFile;
use Dbp\Relay\BlobLibrary\Api\BlobFileStream;
use Symfony\Component\Uid\Uuid;

/**
 * Stores files under workflow-owned prefixes, including before workflow creation.
 * Callers are responsible for user authorization and preventing uploads during cleanup.
 */
final class WorkflowFileService
{
    private const PAGE_SIZE = 100;
    private const FILE_OPTIONS = [BlobApi::INCLUDE_DELETE_AT_OPTION => true];

    public function __construct(private readonly BlobApi $blobApi)
    {
    }

    public function store(string $workflowId, string $filename, string $mimeType, string $contents): string
    {
        $file = new BlobFile();
        $file->setPrefix($this->getPrefix($workflowId));
        $file->setFileName($filename);
        $file->setMimeType($mimeType);
        $file->setFile($contents);

        $identifier = $this->blobApi->addFile($file)->getIdentifier();
        if ($identifier === null || $identifier === '') {
            throw new \RuntimeException('Blob upload returned no file identifier.');
        }

        return $identifier;
    }

    public function get(string $workflowId, string $fileId): BlobFile
    {
        $prefix = $this->getPrefix($workflowId);
        $file = $this->blobApi->getFile($fileId, self::FILE_OPTIONS);
        if ($file->getPrefix() !== $prefix) {
            throw new \RuntimeException('File does not belong to the workflow.');
        }

        return $file;
    }

    public function download(string $workflowId, string $fileId): BlobFileStream
    {
        $this->get($workflowId, $fileId);

        return $this->blobApi->getFileStream($fileId, self::FILE_OPTIONS);
    }

    /**
     * Defaults to the bucket's link_expire_time. The Blob server rejects lifetimes above that limit.
     */
    public function createDownloadUrl(string $workflowId, string $fileId, ?int $ttlSeconds = null): string
    {
        if ($ttlSeconds !== null && $ttlSeconds <= 0) {
            throw new \InvalidArgumentException('Download URL lifetime must be positive.');
        }
        $this->get($workflowId, $fileId);

        $parameters = self::FILE_OPTIONS;
        if ($ttlSeconds !== null) {
            $parameters['expireIn'] = 'PT'.$ttlSeconds.'S';
        }

        return $this->blobApi->createSignedUrl('GET', $parameters, [], $fileId, 'download');
    }

    /**
     * @return iterable<BlobFile>
     */
    public function list(string $workflowId): iterable
    {
        $options = $this->getListOptions($workflowId);
        $page = 1;
        do {
            $files = iterator_to_array($this->blobApi->getFiles($page, self::PAGE_SIZE, $options), false);
            foreach ($files as $file) {
                yield $file;
            }
            ++$page;
        } while (count($files) === self::PAGE_SIZE);
    }

    public function delete(string $workflowId, string $fileId): void
    {
        try {
            $this->get($workflowId, $fileId);
            $this->blobApi->removeFile($fileId, self::FILE_OPTIONS);
        } catch (BlobApiError $error) {
            if ($error->getErrorId() !== BlobApiError::FILE_NOT_FOUND) {
                throw $error;
            }
        }
    }

    public function deleteAll(string $workflowId): void
    {
        $options = $this->getListOptions($workflowId);
        do {
            // Always fetch the first page because deletion shrinks the result set.
            $files = iterator_to_array($this->blobApi->getFiles(1, self::PAGE_SIZE, $options), false);
            foreach ($files as $file) {
                $identifier = $file->getIdentifier();
                if ($identifier === null || $identifier === '') {
                    throw new \RuntimeException('Blob listing returned no file identifier.');
                }
                $this->delete($workflowId, $identifier);
            }
        } while ($files !== []);
    }

    private function getPrefix(string $workflowId): string
    {
        if (!Uuid::isValid($workflowId)) {
            throw new \InvalidArgumentException('Workflow ID must be a UUID.');
        }

        return 'portfolio/workflows/'.Uuid::fromString($workflowId)->toRfc4122();
    }

    private function getListOptions(string $workflowId): array
    {
        return [BlobApi::PREFIX_OPTION => $this->getPrefix($workflowId)] + self::FILE_OPTIONS;
    }
}
