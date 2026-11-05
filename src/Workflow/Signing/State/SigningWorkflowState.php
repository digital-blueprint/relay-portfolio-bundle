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
        if ($this->state->cancelledAt !== null || $this->state->expiredAt !== null || $this->job->iterations === []) {
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
        if (count($this->job->iterations) !== count($this->state->iterations)) {
            throw new \InvalidArgumentException('Job and execution iterations must correspond one-to-one.');
        }
        $stateNumbers = array_map(static fn (SigningIterationState $iteration): int => $iteration->number, $this->state->iterations);
        if (count(array_unique($stateNumbers)) !== count($stateNumbers)) {
            throw new \InvalidArgumentException('Execution iteration numbers must be unique.');
        }
        $previousNumber = -1;
        $taskIds = [];
        foreach ($this->job->iterations as $definition) {
            if ($definition->number <= $previousNumber || $definition->participants === []) {
                throw new \InvalidArgumentException('Job iterations must be ordered, unique and contain participants.');
            }
            $previousNumber = $definition->number;
            $iteration = $this->state->getIteration($definition->number);
            $participantIndices = [];
            foreach ($iteration->tasks as $task) {
                if (!isset($definition->participants[$task->participantIndex]) || isset($participantIndices[$task->participantIndex])) {
                    throw new \InvalidArgumentException('Tasks must reference distinct participants of their iteration.');
                }
                if (trim($task->taskId) === '' || isset($taskIds[$task->taskId])) {
                    throw new \InvalidArgumentException('Task IDs must be non-empty and unique within the workflow.');
                }
                $participantIndices[$task->participantIndex] = true;
                $taskIds[$task->taskId] = true;
            }
            if (count($participantIndices) !== count($definition->participants)) {
                throw new \InvalidArgumentException('Every participant must have a task reference.');
            }
        }
    }
}
