<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Workflow\Signing\State;

/**
 * An internal person's identity snapshot captured when they participate in a workflow.
 * API email identifiers are resolved to internal user IDs before persistence.
 */
final readonly class SigningIdentity
{
    public function __construct(
        public string $email,
        public string $userId,
        public ?string $displayName = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            email: $data['email'],
            userId: $data['userId'],
            displayName: $data['displayName'] ?? null,
        );
    }

    /** @return array{email: string, userId: string, displayName: string|null} */
    public function toArray(): array
    {
        return [
            'email' => $this->email,
            'userId' => $this->userId,
            'displayName' => $this->displayName,
        ];
    }
}
