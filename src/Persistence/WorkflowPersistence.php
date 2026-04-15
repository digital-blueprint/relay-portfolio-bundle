<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Persistence;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'portfolio_workflows')]
#[ORM\Entity]
class WorkflowPersistence
{
    public const STATE_ACTIVE = 'active';
    public const STATE_CANCELLED = 'cancelled';
    public const STATE_DONE = 'done';
    public const STATE_ARCHIVED = 'archived';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 255)]
    private string $id = '';

    #[ORM\Column(type: 'string', length: 255)]
    private string $type = '';

    #[ORM\Column(type: 'string', length: 50)]
    private string $state = self::STATE_ACTIVE;

    #[ORM\Column(type: 'text')]
    private string $internalState = '{}';

    #[ORM\Column(type: 'relay_portfolio_datetime_utc', nullable: true)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'relay_portfolio_datetime_utc', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: 'relay_portfolio_datetime_utc', nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    /**
     * @var Collection<int, TaskPersistence>
     */
    #[ORM\OneToMany(targetEntity: TaskPersistence::class, mappedBy: 'workflow')]
    private Collection $tasks;

    public function __construct()
    {
        $this->tasks = new ArrayCollection();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function setState(string $state): void
    {
        $this->state = $state;
    }

    public function getInternalState(): array
    {
        return json_decode($this->internalState, true, flags: JSON_THROW_ON_ERROR);
    }

    public function setInternalState(array $internalState): void
    {
        $this->internalState = json_encode($internalState, JSON_THROW_ON_ERROR);
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): void
    {
        $this->deletedAt = $deletedAt;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    /**
     * @return Collection<int, TaskPersistence>
     */
    public function getTasks(): Collection
    {
        return $this->tasks;
    }
}
