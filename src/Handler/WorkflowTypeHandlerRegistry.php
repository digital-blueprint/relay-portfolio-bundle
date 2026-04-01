<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Handler;

class WorkflowTypeHandlerRegistry
{
    /** @var array<string, WorkflowTypeHandlerInterface> */
    private array $handlers = [];

    public function addHandler(WorkflowTypeHandlerInterface $handler): void
    {
        $type = $handler->getType();
        if (isset($this->handlers[$type])) {
            throw new \RuntimeException(sprintf("Workflow type handler for type '%s' is already registered.", $type));
        }
        $this->handlers[$type] = $handler;
    }

    public function getHandler(string $type): WorkflowTypeHandlerInterface
    {
        $handler = $this->handlers[$type] ?? null;
        if ($handler === null) {
            throw new \RuntimeException(sprintf("No workflow type handler registered for type '%s'.", $type));
        }

        return $handler;
    }

    public function hasHandler(string $type): bool
    {
        return isset($this->handlers[$type]);
    }

    /**
     * @return WorkflowTypeHandlerInterface[]
     */
    public function getAllHandlers(): array
    {
        return array_values($this->handlers);
    }
}
