<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\ApiPlatform;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Annotation\Groups;

class WorkflowResultMessage
{
    public const TYPE_SUCCESS = 'success';
    public const TYPE_INFO = 'info';
    public const TYPE_WARNING = 'warning';
    public const TYPE_ERROR = 'error';

    public function __construct(
        private readonly string $type,
        private readonly string $title,
        private readonly string $text,
    ) {
    }

    #[ApiProperty]
    #[Groups(['PortfolioWorkflowAction:output'])]
    public function getType(): string
    {
        return $this->type;
    }

    #[ApiProperty]
    #[Groups(['PortfolioWorkflowAction:output'])]
    public function getTitle(): string
    {
        return $this->title;
    }

    #[ApiProperty]
    #[Groups(['PortfolioWorkflowAction:output'])]
    public function getText(): string
    {
        return $this->text;
    }
}
