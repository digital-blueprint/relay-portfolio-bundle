<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Workflow\Signing\State;

/**
 * A null userId denotes an external signer, never an unresolved internal identity.
 * Email and displayName are creation-time snapshots; userId is the internal person ID.
 * Locale is an optional external signer preference, not an identity attribute.
 */
final readonly class SigningParticipant
{
    public function __construct(
        public string $email,
        public ?string $userId = null,
        public ?string $displayName = null,
        public ?string $locale = null,
    ) {
    }

    public function isExternal(): bool
    {
        // Internal approvers have a user ID resolved before workflow creation.
        return $this->userId === null;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            email: $data['email'],
            userId: $data['userId'] ?? null,
            displayName: $data['displayName'] ?? null,
            locale: $data['locale'] ?? null,
        );
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
