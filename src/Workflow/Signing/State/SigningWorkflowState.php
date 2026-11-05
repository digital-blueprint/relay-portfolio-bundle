<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Workflow\Signing\State;

/**
 * An immutable job definition and its mutable execution state.
 * Blob files are owned by the workflow UUID, which is not duplicated here.
 */
final class SigningWorkflowState
{
    public const VERSION = 1;

    public function __construct(
        public readonly SigningJob $job,
        public readonly SigningExecutionState $state,
    ) {
        $this->validateReferences();
    }

    /** @return array{fileId: string, filename: string} */
    public function getCurrentDocument(): array
    {
        $document = $this->job->originalDocument;
        foreach ($this->job->iterations as $definition) {
            $iteration = $this->state->getIteration($definition->number);
            if ($iteration->signedDocument === null) {
                break;
            }
            $document = $iteration->signedDocument;
        }

        return $document;
    }

    public function getCurrentIteration(): ?SigningIterationState
    {
        if (!$this->isFinal()) {
            foreach ($this->job->iterations as $definition) {
                $iteration = $this->state->getIteration($definition->number);
                if ($iteration->signedDocument === null) {
                    return $iteration;
                }
            }
        }

        return null;
    }

    public function isCompleted(): bool
    {
        // Terminal timestamps take precedence over document results.
        if ($this->state->cancelledAt !== null || $this->state->expiredAt !== null) {
            return false;
        }
        foreach ($this->job->iterations as $definition) {
            $iteration = $this->state->getIteration($definition->number);
            if ($iteration->signedDocument === null) {
                return false;
            }
        }

        return true;
    }

    public function isFinal(): bool
    {
        return $this->state->cancelledAt !== null || $this->state->expiredAt !== null || $this->isCompleted();
    }

    public static function fromArray(array $data): self
    {
        if (($data['version'] ?? null) !== self::VERSION) {
            throw new \InvalidArgumentException('Unsupported signing state version.');
        }

        return new self(
            job: SigningJob::fromArray($data['job']),
            state: SigningExecutionState::fromArray($data['state']),
        );
    }

    public function toArray(): array
    {
        return [
            'version' => self::VERSION,
            'job' => $this->job->toArray(),
            'state' => $this->state->toArray(),
        ];
    }

    private function validateReferences(): void
    {
        if (!array_is_list($this->job->iterations) || !array_is_list($this->state->iterations)
            || count($this->job->iterations) !== 2 || count($this->state->iterations) !== 2) {
            throw new \InvalidArgumentException('Signing requires exactly two job steps and their execution records.');
        }
        $stateNumbers = array_map(static fn (SigningIterationState $iteration): int => $iteration->number, $this->state->iterations);
        if (count(array_unique($stateNumbers)) !== count($stateNumbers)) {
            throw new \InvalidArgumentException('Execution iteration numbers must be unique.');
        }
        $taskIds = [];
        foreach ($this->job->iterations as $index => $definition) {
            $kind = $index === 0 ? SigningIterationKind::INTERNAL_APPROVAL : SigningIterationKind::EXTERNAL_SIGNING;
            if ($definition->number !== $index || $definition->kind !== $kind || $definition->participant->isExternal() !== ($index === 1)) {
                throw new \InvalidArgumentException('Signing requires step 0 with an internal approver and step 1 with an external signer.');
            }
            $iteration = $this->state->getIteration($definition->number);
            if (trim($iteration->taskId) === '' || isset($taskIds[$iteration->taskId])) {
                throw new \InvalidArgumentException('Task IDs must be non-empty and unique within the workflow.');
            }
            $taskIds[$iteration->taskId] = true;
        }
    }
}
