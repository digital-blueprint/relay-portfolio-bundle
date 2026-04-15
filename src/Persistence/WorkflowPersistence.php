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
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 255)]
    private string $id = '';

    #[ORM\Column(type: 'string', length: 255)]
    private string $type = '';

    #[ORM\Column(type: 'text')]
    private string $internalState = '{}';

    #[ORM\Column(type: 'relay_portfolio_datetime_utc', nullable: true)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'relay_portfolio_datetime_utc', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: 'relay_portfolio_datetime_utc', nullable: true)]
    private ?\DateTimeImmutable $closedAt = null;

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

    public function getClosedAt(): ?\DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function setClosedAt(?\DateTimeImmutable $closedAt): void
    {
        $this->closedAt = $closedAt;
    }

    public function isActive(): bool
    {
        return $this->closedAt === null;
    }

    public function close(): void
    {
        $this->closedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function reopen(): void
    {
        $this->closedAt = null;
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
