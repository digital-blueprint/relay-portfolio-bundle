<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Workflow\Signing\State;

enum SigningIterationKind: string
{
    case INTERNAL_APPROVAL = 'internal_approval';
    case EXTERNAL_SIGNING = 'external_signing';
}
