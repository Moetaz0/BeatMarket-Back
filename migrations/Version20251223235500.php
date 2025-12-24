<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251223235500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE beat ADD license_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE beat ADD CONSTRAINT FK_D5F9069C460F904B FOREIGN KEY (license_id) REFERENCES license (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_D5F9069C460F904B ON beat (license_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE beat DROP CONSTRAINT FK_D5F9069C460F904B');
        $this->addSql('DROP INDEX IDX_D5F9069C460F904B');
        $this->addSql('ALTER TABLE beat DROP license_id');
    }
}
