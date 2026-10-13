<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Tests;

use Dbp\Relay\PortfolioBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

class WorkflowFileConfigurationTest extends TestCase
{
    private function process(array $config): array
    {
        return (new Processor())->processConfiguration(new Configuration(), [
            ['database_url' => 'sqlite:///:memory:'] + $config,
        ]);
    }

    public function testStorageConfigurationIsRequired(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->process([]);
    }

    public function testConfiguredStorageRequiresBucket(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->process(['blob_library' => []]);
    }
}
