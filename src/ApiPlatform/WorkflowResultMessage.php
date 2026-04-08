<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\ApiPlatform;

use ApiPlatform\Metadata\ApiProperty;
use Dbp\Relay\PortfolioBundle\Handler\WorkflowMessage;
use Symfony\Component\Serializer\Annotation\Groups;

class WorkflowResultMessage
{
    public function __construct(
        private readonly string $type,
        private readonly string $title,
        private readonly string $text,
    ) {
    }

    public static function fromMessage(WorkflowMessage $message): self
    {
        return new self($message->getType(), $message->getTitle(), $message->getText());
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
