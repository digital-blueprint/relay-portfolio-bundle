<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Resources\config;

use Dbp\Relay\PortfolioBundle\Handler\WorkflowTypeHandlerRegistry;
use Dbp\Relay\PortfolioBundle\Service\WorkflowService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();

    $services->load('Dbp\\Relay\\PortfolioBundle\\Authorization\\', '../../Authorization')
        ->autowire()
        ->autoconfigure();

    $services->load('Dbp\\Relay\\PortfolioBundle\\ApiPlatform\\', '../../ApiPlatform')
        ->autowire()
        ->autoconfigure();

    $services->load('Dbp\\Relay\\PortfolioBundle\\Console\\', '../../Console')
        ->autowire()
        ->autoconfigure();

    $services->load('Dbp\\Relay\\PortfolioBundle\\Cron\\', '../../Cron')
        ->autowire()
        ->autoconfigure();

    $services->load('Dbp\\Relay\\PortfolioBundle\\DummyWorkflow\\', '../../DummyWorkflow')
        ->autowire()
        ->autoconfigure();

    $services->set(WorkflowTypeHandlerRegistry::class)
        ->autowire()
        ->autoconfigure();

    $services->set(WorkflowService::class)
        ->autowire()
        ->autoconfigure()
        ->arg('$em', service('doctrine.orm.dbp_relay_portfolio_bundle_entity_manager'));
};
