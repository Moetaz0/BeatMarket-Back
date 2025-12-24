<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add dtype column for single table inheritance
 */
final class Version20251223234131 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add dtype column for Doctrine inheritance mapping';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD dtype VARCHAR(255) NOT NULL DEFAULT \'User\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP COLUMN dtype');
    }
}
