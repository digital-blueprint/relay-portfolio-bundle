<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\SignApi;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class SignServiceCompilerPass implements CompilerPassInterface
{
    public const TAG = 'dbp.relay.portfolio.sign_service';

    public static function register(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new self());
    }

    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(SignServiceRegistry::class)) {
            return;
        }

        $registry = $container->findDefinition(SignServiceRegistry::class);
        $processes = [];
        foreach ($container->findTaggedServiceIds(self::TAG) as $id => $tags) {
            foreach ($tags as $tag) {
                $processId = $tag['process_id'] ?? null;
                if (!is_string($processId) || $processId === '') {
                    throw new \InvalidArgumentException(sprintf("Signing service '%s' must specify a non-empty process_id on its '%s' tag.", $id, self::TAG));
                }
                if (isset($processes[$processId])) {
                    throw new \RuntimeException(sprintf("A signing service for process '%s' is already registered.", $processId));
                }
                $processes[$processId] = true;
                $registry->addMethodCall('addService', [$processId, new Reference($id)]);
            }
        }
    }
}
