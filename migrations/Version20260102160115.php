<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260102160115 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE beat DROP CONSTRAINT fk_d5f9069cad72bf');
        $this->addSql('DROP INDEX idx_d5f9069cad72bf');
        $this->addSql('ALTER TABLE beat RENAME COLUMN beatmaker_id TO user_id');
        $this->addSql('ALTER TABLE beat ADD CONSTRAINT FK_D5F9069CA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_D5F9069CA76ED395 ON beat (user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE beat DROP CONSTRAINT FK_D5F9069CA76ED395');
        $this->addSql('DROP INDEX IDX_D5F9069CA76ED395');
        $this->addSql('ALTER TABLE beat RENAME COLUMN user_id TO beatmaker_id');
        $this->addSql('ALTER TABLE beat ADD CONSTRAINT fk_d5f9069cad72bf FOREIGN KEY (beatmaker_id) REFERENCES beatmaker (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_d5f9069cad72bf ON beat (beatmaker_id)');
    }
}
