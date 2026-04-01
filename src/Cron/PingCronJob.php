<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Cron;

use Dbp\Relay\CoreBundle\Cron\CronJobInterface;
use Dbp\Relay\CoreBundle\Cron\CronOptions;
use Dbp\Relay\PortfolioBundle\Service\WorkflowService;

class PingCronJob implements CronJobInterface
{
    public function __construct(
        private readonly WorkflowService $workflowService,
    ) {
    }

    public function getName(): string
    {
        return 'Portfolio workflow ping';
    }

    public function getInterval(): string
    {
        return '* * * * *';
    }

    public function run(CronOptions $options): void
    {
        $this->workflowService->pingAll();
    }
}
