<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Handler;

class WorkflowActionResult
{
    /**
     * @param array<string, mixed> $customState  Updated custom_state for the workflow
     * @param string|null          $state        New generic state if it changed, null to keep current
     * @param array<string, mixed> $responseData Optional data returned to the client (e.g. redirect URL)
     */
    public function __construct(
        private readonly array $customState,
        private readonly ?string $state = null,
        private readonly array $responseData = [],
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

    /**
     * @return array<string, mixed>
     */
    public function getResponseData(): array
    {
        return $this->responseData;
    }
}
