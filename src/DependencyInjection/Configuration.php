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
                ->arrayNode('sign_api')
                    ->info('HTTP Basic credentials for the Sign endpoints.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('username')
                            ->defaultNull()
                            ->info('The webservice username (e.g. %env(SIGN_API_USER)%).')
                        ->end()
                        ->scalarNode('password')
                            ->defaultNull()
                            ->info('The webservice password/secret (e.g. %env(SIGN_API_PASSWORD)%).')
                        ->end()
                    ->end()
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
