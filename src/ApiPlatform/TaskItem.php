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
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

#[ApiResource(
    shortName: 'PortfolioTask',
    operations: [
        new Get(
            uriTemplate: '/portfolio/tasks/{identifier}',
            openapi: new Operation(tags: ['Portfolio']),
            provider: TaskProvider::class,
        ),
        new GetCollection(
            uriTemplate: '/portfolio/tasks',
            openapi: new Operation(
                tags: ['Portfolio'],
                parameters: [
                    new Parameter(
                        name: 'workflowId',
                        in: 'query',
                        description: 'Required. The workflow ID to list tasks for.',
                        required: true,
                        schema: ['type' => 'string'],
                    ),
                ],
            ),
            provider: TaskProvider::class,
        ),
    ],
    normalizationContext: [
        'groups' => ['PortfolioTask:output'],
        AbstractObjectNormalizer::PRESERVE_EMPTY_OBJECTS => true,
    ],
)]
class TaskItem
{
    #[ApiProperty(identifier: true)]
    #[Groups(['PortfolioTask:output'])]
    private ?string $identifier = null;

    #[Groups(['PortfolioTask:output'])]
    private ?string $workflowId = null;

    #[Groups(['PortfolioTask:output'])]
    #[Context([DateTimeUtcNormalizer::CONTEXT_KEY => true])]
    private ?\DateTimeImmutable $createdAt = null;

    /** @var array<string, mixed> */
    #[Groups(['PortfolioTask:output'])]
    #[Context([Serializer::EMPTY_ARRAY_AS_OBJECT => true])]
    private array $data = [];

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function setIdentifier(?string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getWorkflowId(): ?string
    {
        return $this->workflowId;
    }

    public function setWorkflowId(?string $workflowId): void
    {
        $this->workflowId = $workflowId;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function setData(array $data): void
    {
        $this->data = $data;
    }
}
