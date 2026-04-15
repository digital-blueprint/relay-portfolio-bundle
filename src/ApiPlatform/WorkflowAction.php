<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\ApiPlatform;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\RequestBody;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'PortfolioWorkflowAction',
    operations: [
        new Post(
            uriTemplate: '/portfolio/workflow-actions',
            openapi: new Operation(
                tags: ['Portfolio'],
                requestBody: new RequestBody(
                    content: new \ArrayObject([
                        'application/ld+json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['workflowId', 'action'],
                                'properties' => [
                                    'workflowId' => ['type' => 'string'],
                                    'action' => ['type' => 'string'],
                                    'payload' => ['type' => 'object'],
                                ],
                            ],
                        ],
                    ])
                ),
            ),
            processor: WorkflowActionProcessor::class,
        ),
    ],
    normalizationContext: ['groups' => ['PortfolioWorkflowAction:output']],
    denormalizationContext: ['groups' => ['PortfolioWorkflowAction:input']],
)]
class WorkflowAction
{
    #[ApiProperty(identifier: true)]
    #[Groups(['PortfolioWorkflowAction:output'])]
    private ?string $identifier = null;

    #[Groups(['PortfolioWorkflowAction:input'])]
    private ?string $workflowId = null;

    #[Groups(['PortfolioWorkflowAction:input'])]
    private ?string $action = null;

    /** @var array<string, mixed> */
    #[Groups(['PortfolioWorkflowAction:input'])]
    private array $payload = [];

    #[Groups(['PortfolioWorkflowAction:output'])]
    #[ApiProperty(readableLink: true, genId: false)]
    private ?WorkflowResultMessage $message = null;

    #[Groups(['PortfolioWorkflowAction:output'])]
    private ?string $type = null;

    #[Groups(['PortfolioWorkflowAction:output'])]
    private ?string $url = null;

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

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(?string $action): void
    {
        $this->action = $action;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function setPayload(array $payload): void
    {
        $this->payload = $payload;
    }

    public function getMessage(): ?WorkflowResultMessage
    {
        return $this->message;
    }

    public function setMessage(?WorkflowResultMessage $message): void
    {
        $this->message = $message;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): void
    {
        $this->type = $type;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): void
    {
        $this->url = $url;
    }
}
