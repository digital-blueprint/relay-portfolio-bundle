<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\RequestBody;
use Dbp\Relay\PortfolioBundle\Rest\WorkflowProcessor;
use Dbp\Relay\PortfolioBundle\Rest\WorkflowProvider;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'PortfolioWorkflow',
    types: ['https://schema.org/Workflow'],
    operations: [
        new Get(
            uriTemplate: '/portfolio/workflows/{identifier}',
            openapi: new Operation(
                tags: ['Portfolio'],
            ),
            provider: WorkflowProvider::class
        ),
        new GetCollection(
            uriTemplate: '/portfolio/workflows',
            openapi: new Operation(
                tags: ['Portfolio'],
            ),
            provider: WorkflowProvider::class
        ),
        new Post(
            uriTemplate: '/portfolio/workflows',
            openapi: new Operation(
                tags: ['Portfolio'],
                requestBody: new RequestBody(
                    content: new \ArrayObject([
                        'application/ld+json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'name' => ['type' => 'string'],
                                ],
                                'required' => ['name'],
                            ],
                            'example' => [
                                'name' => 'Example Name',
                            ],
                        ],
                    ])
                )
            ),
            processor: WorkflowProcessor::class
        ),
        new Delete(
            uriTemplate: '/portfolio/workflows/{identifier}',
            openapi: new Operation(
                tags: ['Portfolio'],
            ),
            provider: WorkflowProvider::class,
            processor: WorkflowProcessor::class
        ),
    ],
    normalizationContext: ['groups' => ['PortfolioWorkflow:output']],
    denormalizationContext: ['groups' => ['PortfolioWorkflow:input']]
)]
class Workflow
{
    #[ApiProperty(identifier: true)]
    #[Groups(['PortfolioWorkflow:output'])]
    private ?string $identifier = null;

    #[ApiProperty(iris: ['https://schema.org/name'])]
    #[Groups(['PortfolioWorkflow:output', 'PortfolioWorkflow:input'])]
    private ?string $name;

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function setIdentifier(?string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }
}
