<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Authorization;

use Dbp\Relay\CoreBundle\Authorization\AbstractAuthorizationService;
use Dbp\Relay\PortfolioBundle\DependencyInjection\Configuration;

class AuthorizationService extends AbstractAuthorizationService
{
    /**
     * Throws a 403 if the current user is not allowed to use the portfolio API.
     */
    public function checkCanUse(): void
    {
        $this->denyAccessUnlessIsGrantedRole(Configuration::ROLE_USER);
    }

    /**
     * Returns whether the current user is allowed to use the portfolio API.
     */
    public function getCanUse(): bool
    {
        return $this->isGrantedRole(Configuration::ROLE_USER);
    }
}
