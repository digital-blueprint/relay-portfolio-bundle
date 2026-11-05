<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Workflow\Signing\State;

/** Links a generated task to a participant in the immutable iteration definition. */
final readonly class SigningTask
{
    public function __construct(
        public int $participantIndex,
        public string $taskId,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self($data['participantIndex'], $data['taskId']);
    }

    /** @return array{participantIndex: int, taskId: string} */
    public function toArray(): array
    {
        return ['participantIndex' => $this->participantIndex, 'taskId' => $this->taskId];
    }
}
