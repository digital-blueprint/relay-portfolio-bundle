<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;

final class Version20260401000000 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return 'Initial schema: portfolio_workflows and portfolio_tasks tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE portfolio_workflows (
            id VARCHAR(255) NOT NULL,
            type VARCHAR(255) NOT NULL,
            state VARCHAR(50) NOT NULL,
            custom_state LONGTEXT NOT NULL,
            created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:relay_portfolio_datetime_utc)\',
            updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:relay_portfolio_datetime_utc)\',
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE portfolio_tasks (
            id VARCHAR(255) NOT NULL,
            workflow_id VARCHAR(255) NOT NULL,
            created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:relay_portfolio_datetime_utc)\',
            PRIMARY KEY(id),
            INDEX IDX_PORTFOLIO_TASKS_WORKFLOW (workflow_id),
            CONSTRAINT FK_PORTFOLIO_TASKS_WORKFLOW FOREIGN KEY (workflow_id) REFERENCES portfolio_workflows (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE portfolio_tasks DROP FOREIGN KEY FK_PORTFOLIO_TASKS_WORKFLOW');
        $this->addSql('DROP TABLE portfolio_tasks');
        $this->addSql('DROP TABLE portfolio_workflows');
    }
}
