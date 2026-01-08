<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rename artist_id to user_id in order table
 */
final class Version20260106000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename artist_id column to user_id in order table';
    }

    public function up(Schema $schema): void
    {
        // Rename the column
        $this->addSql('ALTER TABLE "order" RENAME COLUMN artist_id TO user_id');
    }

    public function down(Schema $schema): void
    {
        // Revert the rename
        $this->addSql('ALTER TABLE "order" RENAME COLUMN user_id TO artist_id');
    }
}
