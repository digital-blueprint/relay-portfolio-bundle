<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Workflow\Signing\State;

use Dbp\Relay\PortfolioBundle\SignApi\SignJobState;

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
        public SignJobState $signingStatus = SignJobState::ACTIVE,
        // Most recent retrieval of the final document via the API.
        public ?\DateTimeImmutable $lastRetrievedAt = null,
        public ?string $cancelledByEmail = null,
        public ?string $cancelledByUserId = null,
        public ?string $constituentDisplayName = null,
        public ?\DateTimeImmutable $cancelledAt = null,
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
        if ($this->signingStatus === SignJobState::ACTIVE) {
            foreach ($this->iterations as $iteration) {
                if ($iteration->signedDocument === null) {
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
            documentToSign: $data['documentToSign'],
            iterations: array_map(SigningIteration::fromArray(...), $data['iterations']),
            attachments: $data['attachments'] ?? [],
            description: $data['description'] ?? null,
            referenceId: $data['referenceId'] ?? null,
            expirationDate: isset($data['expirationDate']) ? new \DateTimeImmutable($data['expirationDate']) : null,
            signingStatus: SignJobState::from($data['signingStatus']),
            lastRetrievedAt: isset($data['lastRetrievedAt']) ? new \DateTimeImmutable($data['lastRetrievedAt']) : null,
            cancelledByEmail: $data['cancelledByEmail'] ?? null,
            cancelledByUserId: $data['cancelledByUserId'] ?? null,
            constituentDisplayName: $data['constituentDisplayName'] ?? null,
            cancelledAt: isset($data['cancelledAt']) ? new \DateTimeImmutable($data['cancelledAt']) : null,
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
            'signingStatus' => $this->signingStatus->value,
            'documentToSign' => $this->documentToSign,
            'attachments' => $this->attachments,
            'iterations' => array_map(static fn (SigningIteration $iteration): array => $iteration->toArray(), $this->iterations),
            'description' => $this->description,
            'referenceId' => $this->referenceId,
            'expirationDate' => $this->expirationDate?->format('Y-m-d\TH:i:s.vP'),
            'lastRetrievedAt' => $this->lastRetrievedAt?->format('Y-m-d\TH:i:s.vP'),
            'cancelledAt' => $this->cancelledAt?->format('Y-m-d\TH:i:s.vP'),
            'cancelledByEmail' => $this->cancelledByEmail,
            'cancelledByUserId' => $this->cancelledByUserId,
        ];
    }
}
