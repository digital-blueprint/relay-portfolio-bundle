<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\SignApi;

use Symfony\Component\HttpFoundation\Response;

/**
 * A Sign-level error carrying the HTTP status the connector should receive.
 *
 * The controller turns this into a JSON `{"error": ...}` body with the given
 * status, satisfying the connector's error contract (HTTP >= 400 and/or an
 * `"error":` substring signals failure).
 */
class SignException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $statusCode = Response::HTTP_BAD_REQUEST,
    ) {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public static function jobNotFound(string $processInstanceId): self
    {
        return new self(
            sprintf("Job '%s' was not found.", $processInstanceId),
            Response::HTTP_NOT_FOUND,
        );
    }
}
