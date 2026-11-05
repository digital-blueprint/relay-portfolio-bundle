<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Workflow\Signing\State;

/** Normalized input captured at creation; execution never changes this definition. */
final readonly class SigningJob
{
    /**
     * @param array{fileId: string, filename: string}       $originalDocument
     * @param list<SigningIteration>                        $iterations
     * @param list<array{fileId: string, filename: string}> $attachments
     */
    public function __construct(
        public string $signingProcessId,
        public SigningIdentity $constituent,
        public array $originalDocument,
        public array $iterations,
        public array $attachments = [],
        public ?string $description = null,
        public ?string $referenceId = null,
        public ?\DateTimeImmutable $expirationDate = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            signingProcessId: $data['signingProcessId'],
            constituent: SigningIdentity::fromArray($data['constituent']),
            originalDocument: $data['originalDocument'],
            iterations: array_map(SigningIteration::fromArray(...), $data['iterations']),
            attachments: $data['attachments'] ?? [],
            description: $data['description'] ?? null,
            referenceId: $data['referenceId'] ?? null,
            expirationDate: isset($data['expirationDate']) ? new \DateTimeImmutable($data['expirationDate']) : null,
        );
    }

    public function toArray(): array
    {
        return [
            'signingProcessId' => $this->signingProcessId,
            'constituent' => $this->constituent->toArray(),
            'description' => $this->description,
            'referenceId' => $this->referenceId,
            'expirationDate' => $this->expirationDate?->format('Y-m-d\TH:i:s.vP'),
            'originalDocument' => $this->originalDocument,
            'attachments' => $this->attachments,
            'iterations' => array_map(static fn (SigningIteration $iteration): array => $iteration->toArray(), $this->iterations),
        ];
    }
}
