<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Handler;

use Dbp\Relay\PortfolioBundle\ApiPlatform\WorkflowResultMessage;

class WorkflowActionResult
{
    /**
     * @param array<string, mixed>       $customState Updated custom_state for the workflow
     * @param string|null                $state       New generic state if it changed, null to keep current
     * @param WorkflowResultMessage|null $message     Optional UI message to display to the user
     */
    public function __construct(
        private readonly array $customState,
        private readonly ?string $state = null,
        private readonly ?WorkflowResultMessage $message = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getCustomState(): array
    {
        return $this->customState;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function getMessage(): ?WorkflowResultMessage
    {
        return $this->message;
    }
}
