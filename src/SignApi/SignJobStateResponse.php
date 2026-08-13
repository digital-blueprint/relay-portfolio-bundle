<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\SignApi;

/**
 * The getJobState response returned to the client.
 *
 * The full response is a rich object (processId, processInstanceId,
 * jobMetaData, creationDate, iterations, ...). For now this models only the
 * STATE field, which is all the connector's poller currently needs.
 *
 * TODO: extend with the remaining fields (processId, processInstanceId,
 * jobMetaData, creationDate, endDate, currentIteration, iterations,
 * additionalRecipients, customMap, eventTimestamp, eventType) as the stateful
 * backend is wired up.
 */
class SignJobStateResponse
{
    public function __construct(
        private readonly SignJobState $state,
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
        return [
            'state' => $this->state->value,
        ];
    }
}
