<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Handler;

class StateDisplay
{
    public function __construct(
        private readonly string $label,
        private readonly string $description,
    ) {
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getDescription(): string
    {
        return $this->description;
    }
}
