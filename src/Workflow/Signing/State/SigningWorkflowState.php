<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Workflow\Signing\State;

use Dbp\Relay\PortfolioBundle\SignApi\SignJobState;

/**
 * Data for one signed PDF and unsigned attachments; lifecycle rules live in the handler.
 * Blob files are owned by the workflow UUID, which is not duplicated here.
 */
final class SigningWorkflowState
{
    public const VERSION = 1;

    /**
     * @param list<SigningIteration>                        $iterations
     * @param list<array{fileId: string, filename: string}> $attachments
     */
    public function __construct(
        public string $signingProcessId,
        public string $constituentEmail,
        public string $constituentUserId,
        public string $documentToSignFileId,
        public string $documentToSignFilename,
        public array $iterations,
        public array $attachments = [],
        public ?string $description = null,
        public ?string $referenceId = null,
        public ?\DateTimeImmutable $expirationDate = null,
        public SignJobState $status = SignJobState::ACTIVE,
        public ?\DateTimeImmutable $retrievedAt = null,
        public ?string $cancelledBy = null,
        public ?string $constituentDisplayName = null,
    ) {
    }

    /** @return array{fileId: string, filename: string} */
    public function getCurrentDocument(): array
    {
        $document = ['fileId' => $this->documentToSignFileId, 'filename' => $this->documentToSignFilename];
        foreach ($this->iterations as $iteration) {
            if ($iteration->signedDocumentFileId === null) {
                break;
            }
            $document = [
                'fileId' => $iteration->signedDocumentFileId,
                'filename' => $iteration->signedDocumentFilename ?? $this->documentToSignFilename,
            ];
        }

        return $document;
    }

    public function getCurrentIteration(): ?SigningIteration
    {
        if ($this->status === SignJobState::ACTIVE) {
            foreach ($this->iterations as $iteration) {
                if ($iteration->signedDocumentFileId === null) {
                    return $iteration;
                }
            }
        }

        return null;
    }

    public static function fromArray(array $data): self
    {
        if (($data['version'] ?? null) !== self::VERSION) {
            throw new \InvalidArgumentException('Unsupported signing state version.');
        }

        return new self(
            signingProcessId: $data['signingProcessId'],
            constituentEmail: $data['constituentEmail'],
            constituentUserId: $data['constituentUserId'],
            documentToSignFileId: $data['documentToSignFileId'],
            documentToSignFilename: $data['documentToSignFilename'],
            iterations: array_map(SigningIteration::fromArray(...), $data['iterations']),
            attachments: $data['attachments'] ?? [],
            description: $data['description'] ?? null,
            referenceId: $data['referenceId'] ?? null,
            expirationDate: isset($data['expirationDate']) ? new \DateTimeImmutable($data['expirationDate']) : null,
            status: SignJobState::from($data['status']),
            retrievedAt: isset($data['retrievedAt']) ? new \DateTimeImmutable($data['retrievedAt']) : null,
            cancelledBy: $data['cancelledBy'] ?? null,
            constituentDisplayName: $data['constituentDisplayName'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'version' => self::VERSION,
            'signingProcessId' => $this->signingProcessId,
            'constituentEmail' => $this->constituentEmail,
            'constituentUserId' => $this->constituentUserId,
            'constituentDisplayName' => $this->constituentDisplayName,
            'status' => $this->status->value,
            'documentToSignFileId' => $this->documentToSignFileId,
            'documentToSignFilename' => $this->documentToSignFilename,
            'attachments' => $this->attachments,
            'iterations' => array_map(static fn (SigningIteration $iteration): array => $iteration->toArray(), $this->iterations),
            'description' => $this->description,
            'referenceId' => $this->referenceId,
            'expirationDate' => $this->expirationDate?->format('Y-m-d\TH:i:s.uP'),
            'retrievedAt' => $this->retrievedAt?->format('Y-m-d\TH:i:s.uP'),
            'cancelledBy' => $this->cancelledBy,
        ];
    }
}
