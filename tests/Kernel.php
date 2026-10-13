<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Tests;

use Dbp\Relay\BlobBundle\DbpRelayBlobBundle;
use Dbp\Relay\BlobBundle\TestUtils\BlobTestUtils;
use Dbp\Relay\BlobLibrary\Api\BlobApi;
use Dbp\Relay\CoreBundle\TestUtils\CoreTestKernelTrait;
use Dbp\Relay\PortfolioBundle\DbpRelayPortfolioBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use CoreTestKernelTrait;

    protected function registerAdditionalBundles(): iterable
    {
        yield new DoctrineBundle();
        yield new DoctrineMigrationsBundle();
        yield new DbpRelayBlobBundle();
        yield new DbpRelayPortfolioBundle();
    }

    protected function configureAdditionalContainer(ContainerConfigurator $container): void
    {
        $blobConfig = BlobTestUtils::getTestConfig();
        $blobConfig['buckets'][0]['bucket_id'] = 'portfolio-test';
        $blobConfig['buckets'][0]['link_expire_time'] = 'PT1H';
        $container->extension('dbp_relay_blob', $blobConfig);

        $container->extension('dbp_relay_portfolio', [
            'database_url' => 'sqlite:///:memory:',
            'blob_library' => BlobApi::getCustomModeConfig('portfolio-test')['blob_library'],
            'sign_api' => [
                'api_users' => [
                    // password_hash of 'svc_pass'
                    'svc_user' => ['password_hash' => '$2y$12$C9MtdOiAeuJi9hLHOTm5TOVDLHP3.O.034As8eFyOolAqjIhfVGbu'],
                    // password_hash of 'other_pass'
                    'other_user' => ['password_hash' => '$2y$12$szooYSPfMqDocxv3IxBY/.Nus5H3rM0WAM2KgMersPIpT5YSQTNby'],
                ],
                'processes' => [
                    'foobar42' => [
                        'admins' => ['svc_user'],
                    ],
                    'test_process' => [
                        'admins' => ['svc_user'],
                    ],
                ],
            ],
            'authorization' => [
                'roles' => [
                    'ROLE_USER' => 'true',
                ],
            ],
        ]);

        $container->import('../tests/config/services_test.yaml');
    }
}
