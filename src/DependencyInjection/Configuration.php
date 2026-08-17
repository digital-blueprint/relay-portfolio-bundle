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
                    ->info('HTTP Basic credentials and per-process access control for the Sign endpoints.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('api_users')
                            ->info('The API users allowed to authenticate against the Sign endpoints. The array key is the username.')
                            ->useAttributeAsKey('username')
                            ->arrayPrototype()
                                ->children()
                                    ->scalarNode('password_hash')
                                        ->isRequired()
                                        ->cannotBeEmpty()
                                        ->info('The password hash (as produced by password_hash()) of the webservice password/secret (e.g. %env(SIGN_API_SAP_PROD_PASSWORD_HASH)%).')
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('processes')
                            ->info('Per-process access control. The array key is the processId.')
                            ->useAttributeAsKey('name')
                            ->arrayPrototype()
                                ->children()
                                    ->arrayNode('admins')
                                        ->info('The usernames (from api_users) allowed to use this process.')
                                        ->scalarPrototype()->cannotBeEmpty()->end()
                                    ->end()
                                ->end()
                            ->end()
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
