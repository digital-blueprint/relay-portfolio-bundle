<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\DependencyInjection;

use Dbp\Relay\CoreBundle\Authorization\AuthorizationConfigDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public const ROLE_USER = 'ROLE_USER';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('dbp_relay_portfolio');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('database_url')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->info('The database URL for the portfolio bundle (e.g. mysql://user:pass@host/db)')
                ->end()
            ->end()
            ->append($this->getAuthNode())
        ;

        return $treeBuilder;
    }

    private function getAuthNode(): NodeDefinition
    {
        return AuthorizationConfigDefinition::create()
            ->addRole(
                self::ROLE_USER,
                'false',
                'Returns true if the user is allowed to use the portfolio API.'
            )
            ->getNodeDefinition();
    }
}
