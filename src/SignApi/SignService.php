<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\SignApi;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Symfony\Component\Uid\Uuid;

/**
 * Implements the behaviour behind the four Sign endpoints.
 *
 * This is intentionally *stateless* for now: no signature backend and no
 * persistence are wired up yet. The goal is to exercise the API contract
 * (routing, auth, multipart parsing, response shapes) end-to-end against the
 * signature client and its test client.
 *
 *   - startProcess -> mints a fresh processInstanceId
 *   - getJobState  -> returns a fixed STATE
 *   - cancelJob    -> returns FINISHED_WF_CANCELLED
 *   - getDocument  -> returns a fixed sample PDF
 */
class SignService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * The state getJobState reports for every (unknown-to-us) job. Since there
     * is no real workflow yet, we report ACTIVE so pollers see a non-final job.
     */
    public const DEFAULT_JOB_STATE = SignJobState::ACTIVE;

    private const SIGNED_DOCUMENT_PATH = __DIR__.'/Resources/signed-document.pdf';

    public function __construct()
    {
        $this->logger = new NullLogger();
    }

    /**
     * Creates a signature job and returns its processInstanceId.
     *
     * @param string             $processId      the process configuration key
     *                                           (e.g. "foobar42"); selects the
     *                                           process/workflow to run,
     *                                           distinct from the returned
     *                                           processInstanceId
     * @param SignJobDescription $jobDescription the parsed job model
     * @param string             $documentToSign raw bytes of the main PDF
     * @param string[]           $attachments    raw bytes of each attachment PDF
     *
     * @return string the new processInstanceId (used as the key for all later calls)
     */
    public function startProcess(string $processId, SignJobDescription $jobDescription, string $documentToSign, array $attachments): string
    {
        $processInstanceId = Uuid::v4()->toRfc4122();

        $this->logger->info('Sign startProcess', [
            'processId' => $processId,
            'processInstanceId' => $processInstanceId,
            'referenceId' => $jobDescription->getMetaData()?->getReferenceId(),
            'documentBytes' => strlen($documentToSign),
            'attachmentCount' => count($attachments),
        ]);

        return $processInstanceId;
    }

    /**
     * Resolves a processInstanceId back to the processId of the process that
     * created it.
     *
     * This is used by the endpoints that only receive a processInstanceId
     * (getJobState, getDocument, cancelJob) so the controller can apply the same
     * per-process access control as startProcess.
     *
     * @param string $processInstanceId the id of the job, as returned by startProcess
     *
     * @return string|null the processId, or null if the processInstanceId is unknown
     */
    public function resolveProcessId(string $processInstanceId): ?string
    {
        return 'process49';
    }

    /**
     * Returns the current state of a job.
     *
     * @param string $processInstanceId the id of the job to query, as returned by
     *                                  startProcess
     * @param string $nameClassifier    the name classifier from the request path
     */
    public function getJobState(string $processInstanceId, string $nameClassifier): SignJobStateResponse
    {
        return new SignJobStateResponse(self::DEFAULT_JOB_STATE);
    }

    /**
     * Cancels a job on behalf of the given user and returns the resulting STATE.
     *
     * @param string   $processInstanceId the id of the job to cancel, as
     *                                    returned by startProcess
     * @param SignUser $requestedBy       the constituent requesting the
     *                                    cancellation (from the request body)
     *
     * @return SignJobState the resulting state (e.g. FINISHED_WF_CANCELLED)
     */
    public function cancelJob(string $processInstanceId, SignUser $requestedBy): SignJobState
    {
        $this->logger->info('Sign cancelJob', [
            'processInstanceId' => $processInstanceId,
            'requestedBy' => $requestedBy->getName(),
        ]);

        return SignJobState::FINISHED_WF_CANCELLED;
    }

    /**
     * Returns the signed (or rejected) PDF for a job as raw bytes, or null if no
     * document is available (the controller turns null into an HTTP 404).
     *
     * A real, stateful backend must return null when the job has no downloadable
     * document yet (still active, or an unknown id) so the connector gets a 404.
     * This stateless implementation always returns the fixed sample PDF.
     *
     * @param string $processInstanceId the id of the job whose document to
     *                                  download, as returned by startProcess
     *
     * @return string|null the raw PDF bytes, or null if no document is available
     */
    public function getDocument(string $processInstanceId): ?string
    {
        $bytes = @file_get_contents(self::SIGNED_DOCUMENT_PATH);

        return $bytes === false ? null : $bytes;
    }
}
