<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\SignApi;

/**
 * The getJobState response returned to the client.
 *
 * Optional details are omitted when unavailable; state is always included.
 * Only fields supported by stored workflow data are exposed.
 *
 * @phpstan-type JobStateIteration array{numberOfSignatures: int, invitees: list<array{classifier: string, name: string}>, category: string, iterationNumber: int}
 */
class SignJobStateResponse
{
    /**
     * @param array<string, string>|null   $jobMetaData
     * @param list<JobStateIteration>|null $iterations
     */
    public function __construct(
        private readonly SignJobState $state,
        private readonly ?string $processId = null,
        private readonly ?string $processInstanceId = null,
        private readonly ?array $jobMetaData = null,
        private readonly ?\DateTimeImmutable $creationDate = null,
        private readonly ?int $currentIteration = null,
        private readonly ?array $iterations = null,
    ) {
    }

    public function getState(): SignJobState
    {
        return $this->state;
    }

    /**
     * Serializes to the wire shape defined by the getJobState schema.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'state' => $this->state->value,
            'processId' => $this->processId,
            'processInstanceId' => $this->processInstanceId,
            'jobMetaData' => $this->jobMetaData !== null ? (object) $this->jobMetaData : null,
            'creationDate' => $this->creationDate?->format('Y-m-d\TH:i:s.vP'),
            'currentIteration' => $this->currentIteration,
            'iterations' => $this->iterations,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
