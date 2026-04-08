<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\ApiPlatform;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use Dbp\Relay\CoreBundle\Serializer\DateTimeUtcNormalizer;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Attribute\Context;

#[ApiResource(
    shortName: 'PortfolioWorkflow',
    operations: [
        new Get(
            uriTemplate: '/portfolio/workflows/{identifier}',
            openapi: new Operation(tags: ['Portfolio']),
            provider: WorkflowProvider::class,
        ),
        new GetCollection(
            uriTemplate: '/portfolio/workflows',
            openapi: new Operation(
                tags: ['Portfolio'],
                parameters: [
                    new Parameter(
                        name: 'type',
                        in: 'query',
                        description: 'Filter workflows by type.',
                        required: false,
                        schema: ['type' => 'string'],
                    ),
                ],
            ),
            provider: WorkflowProvider::class,
        ),
    ],
    normalizationContext: ['groups' => ['PortfolioWorkflow:output']],
)]
class WorkflowItem
{
    #[ApiProperty(identifier: true)]
    #[Groups(['PortfolioWorkflow:output'])]
    private ?string $identifier = null;

    #[Groups(['PortfolioWorkflow:output'])]
    private ?string $type = null;

    #[Groups(['PortfolioWorkflow:output'])]
    private ?string $state = null;

    #[Groups(['PortfolioWorkflow:output'])]
    private ?string $name = null;

    #[Groups(['PortfolioWorkflow:output'])]
    private ?string $description = null;

    /** @var array<string, string>|null */
    #[Groups(['PortfolioWorkflow:output'])]
    private ?array $currentStateDisplay = null;

    /** @var array<array{id: string, label: string, type: string, url?: string}>|null */
    #[Groups(['PortfolioWorkflow:output'])]
    private ?array $availableActions = null;

    #[Groups(['PortfolioWorkflow:output'])]
    #[Context([DateTimeUtcNormalizer::CONTEXT_KEY => true])]
    private ?\DateTimeImmutable $createdAt = null;

    #[Groups(['PortfolioWorkflow:output'])]
    #[Context([DateTimeUtcNormalizer::CONTEXT_KEY => true])]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function setIdentifier(?string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): void
    {
        $this->type = $type;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function setState(?string $state): void
    {
        $this->state = $state;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    /**
     * @return array<string, string>|null
     */
    public function getCurrentStateDisplay(): ?array
    {
        return $this->currentStateDisplay;
    }

    /**
     * @param array<string, string>|null $currentStateDisplay
     */
    public function setCurrentStateDisplay(?array $currentStateDisplay): void
    {
        $this->currentStateDisplay = $currentStateDisplay;
    }

    /**
     * @return array<array{id: string, label: string, type: string, url?: string}>|null
     */
    public function getAvailableActions(): ?array
    {
        return $this->availableActions;
    }

    /**
     * @param array<array{id: string, label: string, type: string, url?: string}>|null $availableActions
     */
    public function setAvailableActions(?array $availableActions): void
    {
        $this->availableActions = $availableActions;
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
}
