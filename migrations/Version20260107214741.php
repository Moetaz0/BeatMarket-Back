<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260107214741 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_d5f9069cad72bf');
        $this->addSql('ALTER TABLE beat ADD is_exclusive BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE beat RENAME COLUMN beatmaker_id TO exclusive_owner_id');
        $this->addSql('ALTER TABLE beat ADD CONSTRAINT FK_D5F9069CBA69CE2D FOREIGN KEY (exclusive_owner_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_D5F9069CBA69CE2D ON beat (exclusive_owner_id)');
        $this->addSql('ALTER TABLE license ADD is_exclusive BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE "order" ADD CONSTRAINT FK_F5299398A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER INDEX idx_f5299398b7970cf8 RENAME TO IDX_F5299398A76ED395');
        $this->addSql('ALTER INDEX uniq_1483a5e99f105b75 RENAME TO UNIQ_1483A5E9708DC647');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE license DROP is_exclusive');
        $this->addSql('ALTER TABLE "order" DROP CONSTRAINT FK_F5299398A76ED395');
        $this->addSql('ALTER INDEX idx_f5299398a76ed395 RENAME TO idx_f5299398b7970cf8');
        $this->addSql('ALTER TABLE beat DROP CONSTRAINT FK_D5F9069CBA69CE2D');
        $this->addSql('DROP INDEX IDX_D5F9069CBA69CE2D');
        $this->addSql('ALTER TABLE beat DROP is_exclusive');
        $this->addSql('ALTER TABLE beat RENAME COLUMN exclusive_owner_id TO beatmaker_id');
        $this->addSql('CREATE INDEX idx_d5f9069cad72bf ON beat (beatmaker_id)');
        $this->addSql('ALTER INDEX uniq_1483a5e9708dc647 RENAME TO uniq_1483a5e99f105b75');
    }
}
