<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\SignApi;

/**
 * The job STATE values that flow back to the client
 * through getJobState / cancelJob.
 *
 * States in ACTIVE_STATES are non-final; all others are final.
 */
enum SignJobState: string
{
    // Draft-flow states. These belong to the createDraft / dismissDraft flow,
    // which we don't implement. They are listed here for completeness.
    // DRAFTING is non-final (a draft can still transition into the job flow);
    // DRAFT_DISMISSED is final.
    case DRAFTING = 'DRAFTING';
    case DRAFT_DISMISSED = 'DRAFT_DISMISSED';

    // Job-flow states. ACTIVE / POST_PROCESSING are non-final; the FINISHED_* states
    // are all final.
    case ACTIVE = 'ACTIVE';
    case POST_PROCESSING = 'POST_PROCESSING';
    case FINISHED_SUCCESS = 'FINISHED_SUCCESS';
    case FINISHED_FAILED = 'FINISHED_FAILED';

    // XXX: We have seen these states in the wild, but they are not documented.
    // Maybe we are missing something (??)
    case FINISHED_SIGNATURE_DENIED = 'FINISHED_SIGNATURE_DENIED';
    case FINISHED_TIMEOUT = 'FINISHED_TIMEOUT';
    case FINISHED_WF_CANCELLED = 'FINISHED_WF_CANCELLED';

    /**
     * Non-final states. While a job is in one of these the signature client
     * keeps polling getJobState. DRAFTING is included for completeness.
     *
     * @var self[]
     */
    private const ACTIVE_STATES = [
        self::DRAFTING,
        self::ACTIVE,
        self::POST_PROCESSING,
    ];

    public function isFinal(): bool
    {
        return !in_array($this, self::ACTIVE_STATES, true);
    }
}
