<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rename refresh_token to refresh_tokens for Gesdinet compatibility
 */
final class Version20251224000131 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename refresh_token to refresh_tokens table';
    }

    public function up(Schema $schema): void
    {
        // Drop the old sequence if exists
        $this->addSql('DROP SEQUENCE IF EXISTS refresh_token_id_seq CASCADE');

        // Create new table with correct name
        $this->addSql('CREATE TABLE refresh_tokens (id SERIAL NOT NULL, refresh_token VARCHAR(128) NOT NULL, username VARCHAR(255) NOT NULL, valid TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9BACE7E1C74F2195 ON refresh_tokens (refresh_token)');

        // Drop old table
        if ($schema->hasTable('refresh_token')) {
            $this->addSql('DROP TABLE IF EXISTS refresh_token CASCADE');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE refresh_token_id_seq');
        $this->addSql('CREATE TABLE refresh_token (id INT NOT NULL, refresh_token VARCHAR(128) NOT NULL, username VARCHAR(255) NOT NULL, valid TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C74F2195C74F2195 ON refresh_token (refresh_token)');
        $this->addSql('DROP TABLE IF EXISTS refresh_tokens');
    }
}
