<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Handler;

use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class WorkflowTypeHandlerHelper
{
    private const TASK_ROUTE = '_api_/portfolio/tasks/{identifier}_get';

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly UriSigner $uriSigner,
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

    /**
     * Returns a time-limited signed URL for the given task.
     *
     * The full URI (including path and expiration) is signed with an HMAC-SHA256
     * via Symfony's UriSigner. Use {@see UriSigner::checkRequest()} on the
     * receiving end to verify it.
     */
    public function getSignedTaskUrl(string $taskId, int $ttlSeconds = 3600): string
    {
        return $this->uriSigner->sign(
            $this->getTaskUrl($taskId),
            new \DateInterval('PT'.$ttlSeconds.'S'),
        );
    }
}
