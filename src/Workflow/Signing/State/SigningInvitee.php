<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Workflow\Signing\State;

/**
 * The invitee ID also identifies their task within this iteration.
 */
final class SigningInvitee
{
    public function __construct(
        public string $id,
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
            id: $data['id'],
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
