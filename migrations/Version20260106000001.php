<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Remove Artist and Beatmaker tables and keep only User and Admin
 */
final class Version20260106000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove Artist and Beatmaker tables and migrate to User/Admin only structure';
    }

    public function up(Schema $schema): void
    {
        // Drop the artist and beatmaker tables with CASCADE to handle dependencies
        $this->addSql('DROP TABLE IF EXISTS artist CASCADE');
        $this->addSql('DROP TABLE IF EXISTS beatmaker CASCADE');
    }

    public function down(Schema $schema): void
    {
        // Recreate beatmaker table
        $this->addSql('CREATE TABLE beatmaker (id INT NOT NULL, studio_name VARCHAR(100) DEFAULT NULL, biography VARCHAR(255) DEFAULT NULL, youtube_channel VARCHAR(255) DEFAULT NULL, soundcloud_profile VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE beatmaker ADD CONSTRAINT fk_1599687bf396750 FOREIGN KEY (id) REFERENCES users (id) ON DELETE CASCADE');

        // Recreate artist table
        $this->addSql('CREATE TABLE artist (id INT NOT NULL, genre VARCHAR(100) DEFAULT NULL, biography VARCHAR(255) DEFAULT NULL, spotify_profile VARCHAR(255) DEFAULT NULL, soundcloud_profile VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE artist ADD CONSTRAINT fk_1599687bf396750 FOREIGN KEY (id) REFERENCES users (id) ON DELETE CASCADE');
    }
}

