<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Handler;

class WorkflowActionResult
{
    /**
     * @param array<string, mixed> $internalState Updated internal state for the workflow
     * @param string|null          $state         New generic state if it changed, null to keep current
     * @param WorkflowMessage|null $message       Optional UI message to display to the user; mutually exclusive with $url
     * @param string|null          $url           Optional URL to return to the client (e.g. a signed URL); mutually exclusive with $message
     */
    public function __construct(
        private readonly array $internalState,
        private readonly ?string $state = null,
        private readonly ?WorkflowMessage $message = null,
        private readonly ?string $url = null,
    ) {
        if ($message !== null && $url !== null) {
            throw new \InvalidArgumentException('WorkflowActionResult cannot have both a message and a url set; they are mutually exclusive.');
        }
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

    public function getUrl(): ?string
    {
        return $this->url;
    }
}
