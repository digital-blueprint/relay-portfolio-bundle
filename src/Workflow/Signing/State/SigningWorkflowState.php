<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Workflow\Signing\State;

/**
 * Data for one signed PDF and unsigned attachments; lifecycle rules live in the handler.
 * Blob files are owned by the workflow UUID, which is not duplicated here.
 * The constituent is the requester, not necessarily one of the signers.
 * Constituent identity fields are snapshots resolved when the workflow is created.
 */
final class SigningWorkflowState
{
    public const VERSION = 1;

    /**
     * @param list<SigningIteration>                        $iterations
     * @param list<array{fileId: string, filename: string}> $attachments
     * @param array{fileId: string, filename: string}       $documentToSign
     */
    public function __construct(
        public string $signingProcessId,
        public string $constituentEmail,
        public string $constituentUserId,
        public array $documentToSign,
        public array $iterations,
        public array $attachments = [],
        public ?string $description = null,
        public ?string $referenceId = null,
        public ?\DateTimeImmutable $expirationDate = null,
        // Most recent retrieval of the final document via the API.
        public ?\DateTimeImmutable $lastRetrievedAt = null,
        public ?string $cancelledByEmail = null,
        public ?string $cancelledByUserId = null,
        public ?string $constituentDisplayName = null,
        public ?\DateTimeImmutable $cancelledAt = null,
        // Recorded timeout transition, not merely an elapsed expirationDate.
        public ?\DateTimeImmutable $expiredAt = null,
    ) {
    }

    /** @return array{fileId: string, filename: string} */
    public function getCurrentDocument(): array
    {
        $document = $this->documentToSign;
        foreach ($this->iterations as $iteration) {
            if ($iteration->signedDocument === null) {
                break;
            }
            $document = $iteration->signedDocument;
        }

        return $document;
    }

    public function getCurrentIteration(): ?SigningIteration
    {
        if (!$this->isFinal()) {
            foreach ($this->iterations as $iteration) {
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
        if ($this->cancelledAt !== null || $this->expiredAt !== null || $this->iterations === []) {
            return false;
        }
        foreach ($this->iterations as $iteration) {
            if ($iteration->signedDocument === null) {
                return false;
            }
        }

        return true;
    }

    public function isFinal(): bool
    {
        return $this->cancelledAt !== null || $this->expiredAt !== null || $this->isCompleted();
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
            documentToSign: $data['documentToSign'],
            iterations: array_map(SigningIteration::fromArray(...), $data['iterations']),
            attachments: $data['attachments'] ?? [],
            description: $data['description'] ?? null,
            referenceId: $data['referenceId'] ?? null,
            expirationDate: isset($data['expirationDate']) ? new \DateTimeImmutable($data['expirationDate']) : null,
            lastRetrievedAt: isset($data['lastRetrievedAt']) ? new \DateTimeImmutable($data['lastRetrievedAt']) : null,
            cancelledByEmail: $data['cancelledByEmail'] ?? null,
            cancelledByUserId: $data['cancelledByUserId'] ?? null,
            constituentDisplayName: $data['constituentDisplayName'] ?? null,
            cancelledAt: isset($data['cancelledAt']) ? new \DateTimeImmutable($data['cancelledAt']) : null,
            expiredAt: isset($data['expiredAt']) ? new \DateTimeImmutable($data['expiredAt']) : null,
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
            'documentToSign' => $this->documentToSign,
            'attachments' => $this->attachments,
            'iterations' => array_map(static fn (SigningIteration $iteration): array => $iteration->toArray(), $this->iterations),
            'description' => $this->description,
            'referenceId' => $this->referenceId,
            'expirationDate' => $this->expirationDate?->format('Y-m-d\TH:i:s.vP'),
            'lastRetrievedAt' => $this->lastRetrievedAt?->format('Y-m-d\TH:i:s.vP'),
            'cancelledAt' => $this->cancelledAt?->format('Y-m-d\TH:i:s.vP'),
            'expiredAt' => $this->expiredAt?->format('Y-m-d\TH:i:s.vP'),
            'cancelledByEmail' => $this->cancelledByEmail,
            'cancelledByUserId' => $this->cancelledByUserId,
        ];
    }
}
