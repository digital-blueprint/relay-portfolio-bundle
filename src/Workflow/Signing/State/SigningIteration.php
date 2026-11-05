<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Workflow\Signing\State;

/** One immutable iteration in the job definition, without tasks or results. */
final readonly class SigningIteration
{
    public function __construct(
        public int $number,
        public SigningIterationKind $kind,
        public SigningParticipant $participant,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['number'],
            SigningIterationKind::from($data['kind']),
            SigningParticipant::fromArray($data['participant']),
        );
    }

    public function toArray(): array
    {
        return [
            'number' => $this->number,
            'kind' => $this->kind->value,
            'participant' => $this->participant->toArray(),
        ];
    }
}
