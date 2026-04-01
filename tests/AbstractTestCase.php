<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Tests;

use Dbp\Relay\CoreBundle\TestUtils\TestAuthorizationService;
use Dbp\Relay\PortfolioBundle\Authorization\AuthorizationService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class AbstractTestCase extends KernelTestCase
{
    protected ContainerInterface $container;
    protected TestEntityManager $testEntityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        // getContainer() returns the special test container that exposes private services
        $this->container = static::getContainer();
        TestEntityManager::setUp($this->container);
        $this->testEntityManager = new TestEntityManager($this->container);

        TestAuthorizationService::setUp(
            $this->container->get(AuthorizationService::class),
            TestAuthorizationService::TEST_USER_IDENTIFIER
        );
    }
}
