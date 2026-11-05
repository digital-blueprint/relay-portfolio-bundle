<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Workflow\Signing\State;

/** The single participant's task and output for one step, linked by its number. */
final class SigningIterationState
{
    /**
     * @param array{fileId: string, filename: string}|null $signedDocument
     */
    public function __construct(
        public readonly int $number,
        public readonly string $taskId,
        public ?array $signedDocument = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['number'],
            $data['taskId'],
            $data['signedDocument'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'number' => $this->number,
            'taskId' => $this->taskId,
            'signedDocument' => $this->signedDocument,
        ];
    }
}
