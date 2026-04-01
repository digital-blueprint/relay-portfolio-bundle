<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Persistence;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'portfolio_tasks')]
#[ORM\Entity]
class TaskPersistence
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 255)]
    private string $id = '';

    #[ORM\ManyToOne(targetEntity: WorkflowPersistence::class, inversedBy: 'tasks')]
    #[ORM\JoinColumn(name: 'workflow_id', referencedColumnName: 'id', nullable: false)]
    private ?WorkflowPersistence $workflow = null;

    #[ORM\Column(type: 'relay_portfolio_datetime_utc', nullable: true)]
    private ?\DateTimeImmutable $createdAt = null;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getWorkflow(): ?WorkflowPersistence
    {
        return $this->workflow;
    }

    public function setWorkflow(?WorkflowPersistence $workflow): void
    {
        $this->workflow = $workflow;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }
}
