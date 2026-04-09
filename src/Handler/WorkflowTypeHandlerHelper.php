<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Handler;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class WorkflowTypeHandlerHelper
{
    private const TASK_ROUTE = '_api_/portfolio/tasks/{identifier}_get';

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * Returns an absolute URL to the given task.
     */
    public function getTaskUrl(string $taskId): string
    {
        return $this->urlGenerator->generate(
            self::TASK_ROUTE,
            ['identifier' => $taskId],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }
}
