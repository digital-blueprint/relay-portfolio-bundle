<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Tests\SignApi;

use Dbp\Relay\PortfolioBundle\SignApi\SignJobDescription;
use Dbp\Relay\PortfolioBundle\SignApi\SignServiceInterface;
use Dbp\Relay\PortfolioBundle\SignApi\SignServiceRegistry;
use PHPUnit\Framework\TestCase;

class SignServiceRegistryTest extends TestCase
{
    public function testFindsRegisteredImplementation(): void
    {
        $registry = new SignServiceRegistry();
        $registry->addService('other', new TestSignService());
        $service = new TestSignService();
        $registry->addService('test_process', $service);
        $instanceId = $service->startProcess('test_process', $this->createStub(SignJobDescription::class), '%PDF', []);

        $this->assertSame($service, $registry->getService('test_process'));
        $this->assertSame('test_process', $registry->resolveProcessId($instanceId));
        $this->assertNull($registry->getService('unknown'));
    }

    public function testQueriesSharedImplementationOnlyOnce(): void
    {
        $service = $this->createMock(SignServiceInterface::class);
        $service->expects($this->once())->method('resolveProcessId')->with('instance')->willReturn('second');
        $registry = new SignServiceRegistry();
        $registry->addService('first', $service);
        $registry->addService('second', $service);

        $this->assertSame('second', $registry->resolveProcessId('instance'));
    }

    public function testUnknownInstanceReturnsNull(): void
    {
        $registry = new SignServiceRegistry();
        $registry->addService('test_process', new TestSignService());

        $this->assertNull($registry->resolveProcessId('unknown'));
    }

    public function testCannotResolveToAnotherImplementationsProcess(): void
    {
        $service = $this->createMock(SignServiceInterface::class);
        $service->method('resolveProcessId')->willReturn('other');
        $registry = new SignServiceRegistry();
        $registry->addService('test_process', $service);
        $registry->addService('other', $this->createStub(SignServiceInterface::class));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not registered for');
        $registry->resolveProcessId('instance');
    }

    public function testAmbiguousInstanceIsRejected(): void
    {
        $registry = new SignServiceRegistry();
        foreach (['first', 'second'] as $processId) {
            $service = $this->createMock(SignServiceInterface::class);
            $service->method('resolveProcessId')->willReturn($processId);
            $registry->addService($processId, $service);
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Multiple signing services claim');
        $registry->resolveProcessId('instance');
    }
}
