<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Workflow\Signing\State;

/** One immutable iteration in the job definition, without tasks or results. */
final readonly class SigningIteration
{
    /**
     * @param list<SigningParticipant> $participants
     */
    public function __construct(
        public int $number,
        public SigningIterationKind $kind,
        public array $participants,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['number'],
            SigningIterationKind::from($data['kind']),
            array_map(SigningParticipant::fromArray(...), $data['participants']),
        );
    }

    public function toArray(): array
    {
        return [
            'number' => $this->number,
            'kind' => $this->kind->value,
            'participants' => array_map(static fn (SigningParticipant $participant): array => $participant->toArray(), $this->participants),
        ];
    }
}
