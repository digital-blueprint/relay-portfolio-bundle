<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\DependencyInjection;

use Dbp\Relay\CoreBundle\Doctrine\DateTimeImmutableUtcType;
use Dbp\Relay\CoreBundle\Extension\ExtensionTrait;
use Dbp\Relay\PortfolioBundle\Authorization\AuthorizationService;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

class DbpRelayPortfolioExtension extends ConfigurableExtension implements PrependExtensionInterface
{
    use ExtensionTrait;

    public function loadInternal(array $mergedConfig, ContainerBuilder $container): void
    {
        $this->addResourceClassDirectory($container, __DIR__.'/../ApiPlatform');

        $loader = new PhpFileLoader(
            $container,
            new FileLocator(__DIR__.'/../Resources/config')
        );
        $loader->load('services.php');

        $typeDefinition = $container->getParameter('doctrine.dbal.connection_factory.types');
        assert(is_array($typeDefinition));
        $typeDefinition['relay_portfolio_datetime_utc'] = ['class' => DateTimeImmutableUtcType::class];
        $container->setParameter('doctrine.dbal.connection_factory.types', $typeDefinition);

        $container->getDefinition(AuthorizationService::class)
            ->addMethodCall('setConfig', [$mergedConfig]);
    }

    public function prepend(ContainerBuilder $container): void
    {
        $configs = $container->getExtensionConfig($this->getAlias());
        $config = $this->processConfiguration(new Configuration(), $configs);

        foreach (['doctrine', 'doctrine_migrations'] as $extKey) {
            if (!$container->hasExtension($extKey)) {
                throw new \Exception("'".$this->getAlias()."' requires the '$extKey' bundle to be loaded!");
            }
        }

        $container->prependExtensionConfig('doctrine', [
            'dbal' => [
                'connections' => [
                    'dbp_relay_portfolio_bundle' => [
                        'url' => $config['database_url'] ?? '',
                    ],
                ],
            ],
            'orm' => [
                'entity_managers' => [
                    'dbp_relay_portfolio_bundle' => [
                        'naming_strategy' => 'doctrine.orm.naming_strategy.underscore_number_aware',
                        'connection' => 'dbp_relay_portfolio_bundle',
                        'mappings' => [
                            'dbp_relay_portfolio' => [
                                'type' => 'attribute',
                                'dir' => __DIR__.'/../Persistence',
                                'prefix' => 'Dbp\Relay\PortfolioBundle\Persistence',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->registerEntityManager($container, 'dbp_relay_portfolio_bundle');

        $container->prependExtensionConfig('doctrine_migrations', [
            'migrations_paths' => [
                'Dbp\Relay\PortfolioBundle\Migrations' => __DIR__.'/../Migrations',
            ],
        ]);
    }
}
