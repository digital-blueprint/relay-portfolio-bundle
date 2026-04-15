<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Cron;

use Dbp\Relay\CoreBundle\Cron\CronJobInterface;
use Dbp\Relay\CoreBundle\Cron\CronOptions;
use Dbp\Relay\PortfolioBundle\Service\WorkflowService;

class CleanupCronJob implements CronJobInterface
{
    public function __construct(
        private readonly WorkflowService $workflowService,
    ) {
    }

    public function getName(): string
    {
        return 'Portfolio workflow cleanup';
    }

    public function getInterval(): string
    {
        return '*/5 * * * *';
    }

    public function run(CronOptions $options): void
    {
        $this->workflowService->cleanupAll();
    }
}
