<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Tests\SignApi;

use Dbp\Relay\PortfolioBundle\SignApi\SignServiceCompilerPass;
use Dbp\Relay\PortfolioBundle\SignApi\SignServiceRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class SignServiceCompilerPassTest extends TestCase
{
    public function testRegistersProcessAttributes(): void
    {
        $container = new ContainerBuilder();
        SignServiceCompilerPass::register($container);
        $container->register(SignServiceRegistry::class)->setPublic(true);
        $container->register('signing', TestSignService::class)->setAutoconfigured(true);
        $container->compile();

        $registry = $container->get(SignServiceRegistry::class);
        $this->assertInstanceOf(SignServiceRegistry::class, $registry);
        $this->assertInstanceOf(TestSignService::class, $registry->getService('test_process'));
        $this->assertSame($registry->getService('test_process'), $registry->getService('foobar42'));
    }

    public function testRejectsMissingProcessId(): void
    {
        $container = new ContainerBuilder();
        $container->register(SignServiceRegistry::class);
        $container->register('signing', TestSignService::class)->addTag(SignServiceCompilerPass::TAG);

        $this->expectException(\InvalidArgumentException::class);
        (new SignServiceCompilerPass())->process($container);
    }

    public function testRejectsDuplicateProcessesDuringCompilation(): void
    {
        $container = new ContainerBuilder();
        $container->register(SignServiceRegistry::class);
        foreach (['first', 'second'] as $id) {
            $container->register($id, TestSignService::class)
                ->addTag(SignServiceCompilerPass::TAG, ['process_id' => 'test_process']);
        }

        $this->expectException(\RuntimeException::class);
        (new SignServiceCompilerPass())->process($container);
    }
}
