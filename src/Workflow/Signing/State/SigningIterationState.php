<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Workflow\Signing\State;

/** Runtime task references and the output of one iteration, linked by its number. */
final class SigningIterationState
{
    /**
     * @param list<SigningTask>                            $tasks
     * @param array{fileId: string, filename: string}|null $signedDocument
     */
    public function __construct(
        public readonly int $number,
        public readonly array $tasks,
        public ?array $signedDocument = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['number'],
            array_map(SigningTask::fromArray(...), $data['tasks']),
            $data['signedDocument'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'number' => $this->number,
            'tasks' => array_map(static fn (SigningTask $task): array => $task->toArray(), $this->tasks),
            'signedDocument' => $this->signedDocument,
        ];
    }
}
