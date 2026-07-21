<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Handler;

/**
 * Result of a handler's getRenderResponse(): the HTML (or other) content to
 * serve at the internal /portfolio/_render endpoint, e.g. for embedding in an
 * iframe on the client.
 */
class RenderResult
{
    public function __construct(
        private readonly string $html,
        private readonly string $contentType = 'text/html; charset=utf-8',
    ) {
    }

    public function getHtml(): string
    {
        return $this->html;
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }
}
