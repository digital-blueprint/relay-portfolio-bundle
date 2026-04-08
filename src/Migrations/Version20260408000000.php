<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;

final class Version20260408000000 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return 'Rename custom_state to internal_state in portfolio_workflows';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE portfolio_workflows RENAME COLUMN custom_state TO internal_state');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE portfolio_workflows RENAME COLUMN internal_state TO custom_state');
    }
}
