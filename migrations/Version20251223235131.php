<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fix schema inconsistencies
 */
final class Version20251223235131 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix schema inconsistencies - remove role column and fix refresh_token table';
    }

    public function up(Schema $schema): void
    {
        // Remove the duplicate role column added by another migration
        if ($schema->getTable('users')->hasColumn('role')) {
            $this->addSql('ALTER TABLE users DROP COLUMN role');
        }

        // Fix dtype column - remove DEFAULT
        $this->addSql('ALTER TABLE users ALTER dtype DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD role VARCHAR(255) NOT NULL DEFAULT \'ROLE_USER\'');
        $this->addSql('ALTER TABLE users ALTER dtype SET DEFAULT \'User\'');
    }
}
