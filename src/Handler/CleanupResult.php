<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Handler;

final class CleanupResult
{
    /**
     * @param array<string, mixed> $internalState Updated internal state to persist. Pass
     *                                            $workflow->getInternalState() unchanged if
     *                                            no update is needed.
     */
    public function __construct(
        private readonly bool $done,
        private readonly array $internalState,
    ) {
    }

    /**
     * Returns true when all external cleanup is complete and the workflow may be
     * hard-deleted from the database.
     */
    public function isDone(): bool
    {
        return $this->done;
    }

    /**
     * @return array<string, mixed>
     */
    public function getInternalState(): array
    {
        return $this->internalState;
    }
}
