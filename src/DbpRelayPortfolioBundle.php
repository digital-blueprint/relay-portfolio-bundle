<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle;

use Dbp\Relay\PortfolioBundle\Handler\WorkflowTypeHandlerCompilerPass;
use Dbp\Relay\PortfolioBundle\SignApi\SignServiceCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class DbpRelayPortfolioBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        WorkflowTypeHandlerCompilerPass::register($container);
        SignServiceCompilerPass::register($container);
    }
}
