<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\SignApi;

class SignServiceRegistry
{
    /** @var array<string, SignServiceInterface> */
    private array $services = [];

    public function addService(string $processId, SignServiceInterface $service): void
    {
        if ($processId === '') {
            throw new \InvalidArgumentException('The signing process ID must not be empty.');
        }
        if (isset($this->services[$processId])) {
            throw new \RuntimeException(sprintf("A signing service for process '%s' is already registered.", $processId));
        }
        $this->services[$processId] = $service;
    }

    public function getService(string $processId): ?SignServiceInterface
    {
        return $this->services[$processId] ?? null;
    }

    public function resolveProcessId(string $processInstanceId): ?string
    {
        $resolvedProcessId = null;
        $visited = [];
        foreach ($this->services as $service) {
            // An implementation can be registered for multiple processes; query it only once.
            $serviceId = spl_object_id($service);
            if (isset($visited[$serviceId])) {
                continue;
            }
            $visited[$serviceId] = true;

            $processId = $service->resolveProcessId($processInstanceId);
            if ($processId === null) {
                continue;
            }
            if (($this->services[$processId] ?? null) !== $service) {
                throw new \RuntimeException(sprintf("A signing service resolved job '%s' to a process it is not registered for: '%s'.", $processInstanceId, $processId));
            }
            if ($resolvedProcessId !== null) {
                throw new \RuntimeException(sprintf("Multiple signing services claim job '%s'.", $processInstanceId));
            }
            $resolvedProcessId = $processId;
        }

        return $resolvedProcessId;
    }
}
