<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\DependencyInjection;

use Dbp\Relay\CoreBundle\Extension\ExtensionTrait;
use Dbp\Relay\PortfolioBundle\Service\WorkflowService;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

class DbpRelayPortfolioExtension extends ConfigurableExtension
{
    use ExtensionTrait;

    public function loadInternal(array $mergedConfig, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__.'/../Resources/config')
        );
        $loader->load('services.yaml');

        $this->addResourceClassDirectory($container, __DIR__.'/../Entity');

        $definition = $container->getDefinition(WorkflowService::class);
        $definition->addMethodCall('setConfig', [$mergedConfig]);
    }
}
