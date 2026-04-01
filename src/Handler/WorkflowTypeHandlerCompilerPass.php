<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Handler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class WorkflowTypeHandlerCompilerPass implements CompilerPassInterface
{
    public const TAG = 'dbp.relay.portfolio.workflow_type_handler';

    public static function register(ContainerBuilder $container): void
    {
        $container->registerForAutoconfiguration(WorkflowTypeHandlerInterface::class)
            ->addTag(self::TAG);
        $container->addCompilerPass(new self());
    }

    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(WorkflowTypeHandlerRegistry::class)) {
            return;
        }

        $registry = $container->findDefinition(WorkflowTypeHandlerRegistry::class);
        foreach (array_keys($container->findTaggedServiceIds(self::TAG)) as $id) {
            $registry->addMethodCall('addHandler', [new Reference($id)]);
        }
    }
}
