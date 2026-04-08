<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Handler;

class WorkflowActionResult
{
    /**
     * @param array<string, mixed> $internalState Updated internal state for the workflow
     * @param string|null          $state         New generic state if it changed, null to keep current
     * @param WorkflowMessage|null $message       Optional UI message to display to the user
     */
    public function __construct(
        private readonly array $internalState,
        private readonly ?string $state = null,
        private readonly ?WorkflowMessage $message = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getInternalState(): array
    {
        return $this->internalState;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function getMessage(): ?WorkflowMessage
    {
        return $this->message;
    }
}
