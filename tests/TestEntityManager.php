<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Tests;

use Dbp\Relay\CoreBundle\TestUtils\TestEntityManager as CoreTestEntityManager;
use Dbp\Relay\PortfolioBundle\Persistence\TaskPersistence;
use Dbp\Relay\PortfolioBundle\Persistence\WorkflowPersistence;
use Doctrine\ORM\EntityManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class TestEntityManager extends CoreTestEntityManager
{
    public const ENTITY_MANAGER_ID = 'dbp_relay_portfolio_bundle';

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container, self::ENTITY_MANAGER_ID);
    }

    public static function setUp(ContainerInterface $container): EntityManager
    {
        return self::setUpEntityManager($container, self::ENTITY_MANAGER_ID);
    }

    public function addWorkflow(string $id, string $type, bool $active = true, array $internalState = [], ?\DateTimeImmutable $deletedAt = null, ?\DateTimeImmutable $createdAt = null): WorkflowPersistence
    {
        $now = $createdAt ?? new \DateTimeImmutable();
        $workflow = new WorkflowPersistence();
        $workflow->setId($id);
        $workflow->setType($type);
        if (!$active) {
            $workflow->close();
        }
        $workflow->setInternalState($internalState);
        $workflow->setCreatedAt($now);
        $workflow->setUpdatedAt($now);
        $workflow->setDeletedAt($deletedAt);
        $this->saveEntity($workflow);

        return $workflow;
    }

    public function addTask(string $id, WorkflowPersistence $workflow): TaskPersistence
    {
        $task = new TaskPersistence();
        $task->setId($id);
        $task->setWorkflow($workflow);
        $task->setCreatedAt(new \DateTimeImmutable());
        $this->saveEntity($task);

        return $task;
    }
}
