<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;

final class Version20260415000000 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return 'Add deleted_at column to portfolio_workflows for soft-delete support';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE portfolio_workflows ADD deleted_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE portfolio_workflows DROP COLUMN deleted_at');
    }
}
