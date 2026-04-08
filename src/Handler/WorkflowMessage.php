<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Handler;

class WorkflowMessage
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

    public function getType(): string
    {
        return $this->type;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getText(): string
    {
        return $this->text;
    }
}
