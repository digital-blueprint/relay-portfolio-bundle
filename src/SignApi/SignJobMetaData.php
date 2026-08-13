<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\SignApi;

/**
 * Optional job metadata carried in jobDescription.metaData:
 *
 *   {
 *     "expirationDate": "2026-09-01T22:59:00.000+0100",
 *     "description": "Please sign",
 *     "referenceId": "CASE-123"
 *   }
 *
 * All fields are optional.
 */
class SignJobMetaData
{
    public function __construct(
        private readonly ?string $expirationDate = null,
        private readonly ?string $description = null,
        private readonly ?string $referenceId = null,
    ) {
    }

    /**
     * ISO date "yyyy-MM-ddTHH:mm:ss.SSS±ZZZZ"; drives reminders and auto-expiry.
     */
    public function getExpirationDate(): ?string
    {
        return $this->expirationDate;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Free reference (the external case number), visible only in job details.
     */
    public function getReferenceId(): ?string
    {
        return $this->referenceId;
    }

    /**
     * Parses metadata from a decoded JSON array. Missing keys become null;
     * present-but-non-string values are an error.
     *
     * @param array<string, mixed> $data
     *
     * @throws SignException if a present field is not a string
     */
    public static function fromArray(array $data): self
    {
        return new self(
            expirationDate: SignUtils::optionalString($data, 'expirationDate'),
            description: SignUtils::optionalString($data, 'description'),
            referenceId: SignUtils::optionalString($data, 'referenceId'),
        );
    }
}
