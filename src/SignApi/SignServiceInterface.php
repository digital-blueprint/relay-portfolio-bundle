<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\SignApi;

interface SignServiceInterface
{
    /**
     * Starts a job for the given process configuration and returns its instance ID.
     * Instance IDs must be unique across all registered implementations.
     *
     * @param string[] $attachments Raw bytes of each attachment PDF
     */
    public function startProcess(string $processId, SignJobDescription $jobDescription, string $documentToSign, array $attachments): string;

    /**
     * Returns the process ID for an instance owned by this implementation.
     * Returns null for unknown instances, including those owned by other implementations.
     * The returned process ID must be registered to this implementation.
     */
    public function resolveProcessId(string $processInstanceId): ?string;

    public function getJobState(string $processInstanceId, string $nameClassifier): SignJobStateResponse;

    public function cancelJob(string $processInstanceId, SignUser $requestedBy): SignJobState;

    /**
     * Returns the raw PDF bytes, or null if no document is available yet.
     */
    public function getDocument(string $processInstanceId): ?string;
}
