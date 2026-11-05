<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\SignApi;

enum SignPositionType: string
{
    case INVISIBLE = 'INVISIBLE';
    case SIGNATURE_FIELD = 'SIGNATURE_FIELD';
    case SIGNATURE_PAGE = 'SIGNATURE_PAGE';
    case ON_PLACEHOLDER = 'ON_PLACEHOLDER';
    case DIRECT_POSITION = 'DIRECT_POSITION';
}
