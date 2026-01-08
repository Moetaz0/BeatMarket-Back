<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260108120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove Admin JOINED inheritance artifacts (drop admin table and dtype column)';
    }

    public function up(Schema $schema): void
    {
        // Admin is now represented as a regular User row with ROLE_ADMIN.
        // Remove old inheritance artifacts.
        $this->addSql('DROP TABLE IF EXISTS admin');
        $this->addSql('ALTER TABLE users DROP COLUMN IF EXISTS dtype');
    }

    public function down(Schema $schema): void
    {
        // Recreate dtype column (default User) and admin table (no data restoration).
        $this->addSql("ALTER TABLE users ADD dtype VARCHAR(255) NOT NULL DEFAULT 'User'");
        $this->addSql('CREATE TABLE admin (id INT NOT NULL, permissions VARCHAR(255) DEFAULT NULL, department VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE admin ADD CONSTRAINT FK_880E0D76BF396750 FOREIGN KEY (id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
