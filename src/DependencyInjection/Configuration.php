<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('dbp_relay_portfolio');

        // append your config definition here

        return $treeBuilder;
    }
}
