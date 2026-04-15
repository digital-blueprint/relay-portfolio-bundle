<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Handler;

final class WorkflowData
{
    public const STATE_ACTIVE = 'active';
    public const STATE_CANCELLED = 'cancelled';
    public const STATE_DONE = 'done';
    public const STATE_ARCHIVED = 'archived';

    /**
     * @param array<string, mixed> $internalState
     */
    public function __construct(
        private readonly string $id,
        private readonly string $type,
        private readonly string $state,
        private readonly array $internalState,
        private readonly \DateTimeImmutable $createdAt,
        private readonly ?\DateTimeImmutable $deletedAt = null,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getState(): string
    {
        return $this->state;
    }

    /**
     * @return array<string, mixed>
     */
    public function getInternalState(): array
    {
        return $this->internalState;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }
}
