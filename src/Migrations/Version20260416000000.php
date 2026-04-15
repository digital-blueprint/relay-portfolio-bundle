<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;

final class Version20260416000000 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return 'Replace state column with closed_at datetime column; migrate non-active rows to closed';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE portfolio_workflows ADD closed_at DATETIME DEFAULT NULL');
        // Migrate existing non-active workflows: mark them as closed with the current timestamp
        $this->addSql("UPDATE portfolio_workflows SET closed_at = updated_at WHERE state != 'active'");
        $this->addSql('ALTER TABLE portfolio_workflows DROP COLUMN state');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE portfolio_workflows ADD state VARCHAR(50) NOT NULL DEFAULT 'active'");
        // Restore state from closed_at: closed_at IS NOT NULL → 'done', NULL → 'active'
        $this->addSql("UPDATE portfolio_workflows SET state = 'done' WHERE closed_at IS NOT NULL");
        $this->addSql('ALTER TABLE portfolio_workflows DROP COLUMN closed_at');
    }
}
