<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Workflow\Signing\State;

/** Mutable execution results and lifecycle facts; contains no job definition data. */
final class SigningExecutionState
{
    /** @param list<SigningIterationState> $iterations */
    public function __construct(
        public readonly array $iterations,
        public ?\DateTimeImmutable $cancelledAt = null,
        public ?SigningIdentity $cancelledBy = null,
        // Recorded timeout transition, not merely an elapsed job deadline.
        public ?\DateTimeImmutable $expiredAt = null,
        public ?\DateTimeImmutable $lastRetrievedAt = null,
    ) {
    }

    public function getIteration(int $number): SigningIterationState
    {
        foreach ($this->iterations as $iteration) {
            if ($iteration->number === $number) {
                return $iteration;
            }
        }

        throw new \InvalidArgumentException(sprintf('Missing execution state for iteration %d.', $number));
    }

    public static function fromArray(array $data): self
    {
        return new self(
            iterations: array_map(SigningIterationState::fromArray(...), $data['iterations']),
            cancelledAt: isset($data['cancelledAt']) ? new \DateTimeImmutable($data['cancelledAt']) : null,
            cancelledBy: isset($data['cancelledBy']) ? SigningIdentity::fromArray($data['cancelledBy']) : null,
            expiredAt: isset($data['expiredAt']) ? new \DateTimeImmutable($data['expiredAt']) : null,
            lastRetrievedAt: isset($data['lastRetrievedAt']) ? new \DateTimeImmutable($data['lastRetrievedAt']) : null,
        );
    }

    public function toArray(): array
    {
        return [
            'iterations' => array_map(static fn (SigningIterationState $iteration): array => $iteration->toArray(), $this->iterations),
            'cancelledAt' => $this->cancelledAt?->format('Y-m-d\TH:i:s.vP'),
            'cancelledBy' => $this->cancelledBy?->toArray(),
            'expiredAt' => $this->expiredAt?->format('Y-m-d\TH:i:s.vP'),
            'lastRetrievedAt' => $this->lastRetrievedAt?->format('Y-m-d\TH:i:s.vP'),
        ];
    }
}
