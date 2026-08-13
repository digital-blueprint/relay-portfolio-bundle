<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\SignApi;

/**
 * The "classifier" of a user, i.e. how the "name" field should be interpreted.
 */
enum SignClassifier: string
{
    /** The only classifier this integration uses (per the docs, max 1 email). */
    case EMAIL = 'EMAIL';
    /** An internal user id. */
    case ID = 'ID';
    /** A user principal name. */
    case UPN = 'UPN';
}
