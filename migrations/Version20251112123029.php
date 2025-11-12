<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251112123029 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE admin (id INT NOT NULL, permissions VARCHAR(255) DEFAULT NULL, department VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE artist (id INT NOT NULL, genre VARCHAR(100) DEFAULT NULL, biography VARCHAR(255) DEFAULT NULL, spotify_profile VARCHAR(255) DEFAULT NULL, soundcloud_profile VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE beat (id SERIAL NOT NULL, beatmaker_id INT DEFAULT NULL, title VARCHAR(255) NOT NULL, description VARCHAR(255) DEFAULT NULL, file_url VARCHAR(255) NOT NULL, cover_image VARCHAR(255) DEFAULT NULL, price DOUBLE PRECISION NOT NULL, genre VARCHAR(50) NOT NULL, bpm INT NOT NULL, key VARCHAR(10) DEFAULT NULL, uploaded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_D5F9069CAD72BF ON beat (beatmaker_id)');
        $this->addSql('CREATE TABLE beatmaker (id INT NOT NULL, studio_name VARCHAR(100) DEFAULT NULL, biography VARCHAR(255) DEFAULT NULL, youtube_channel VARCHAR(255) DEFAULT NULL, soundcloud_profile VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE "order" (id SERIAL NOT NULL, artist_id INT DEFAULT NULL, total_amount DOUBLE PRECISION NOT NULL, status VARCHAR(50) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, paid_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_F5299398B7970CF8 ON "order" (artist_id)');
        $this->addSql('CREATE TABLE order_item (id SERIAL NOT NULL, order_id INT DEFAULT NULL, beat_id INT DEFAULT NULL, price DOUBLE PRECISION NOT NULL, quantity INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_52EA1F098D9F6D38 ON order_item (order_id)');
        $this->addSql('CREATE INDEX IDX_52EA1F0973694F ON order_item (beat_id)');
        $this->addSql('CREATE TABLE users (id SERIAL NOT NULL, email VARCHAR(100) NOT NULL, password VARCHAR(255) NOT NULL, username VARCHAR(100) NOT NULL, phone VARCHAR(15) DEFAULT NULL, profile_picture VARCHAR(255) DEFAULT NULL, user_role VARCHAR(20) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, is_verified BOOLEAN NOT NULL, role VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9E7927C74 ON users (email)');
        $this->addSql('CREATE TABLE wallet (id SERIAL NOT NULL, user_id INT DEFAULT NULL, balance DOUBLE PRECISION NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_7C68921FA76ED395 ON wallet (user_id)');
        $this->addSql('CREATE TABLE wallet_transaction (id SERIAL NOT NULL, wallet_id INT DEFAULT NULL, amount DOUBLE PRECISION NOT NULL, type VARCHAR(20) NOT NULL, description VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, reference VARCHAR(50) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_7DAF972712520F3 ON wallet_transaction (wallet_id)');
        $this->addSql('ALTER TABLE admin ADD CONSTRAINT FK_880E0D76BF396750 FOREIGN KEY (id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE artist ADD CONSTRAINT FK_1599687BF396750 FOREIGN KEY (id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE beat ADD CONSTRAINT FK_D5F9069CAD72BF FOREIGN KEY (beatmaker_id) REFERENCES beatmaker (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE beatmaker ADD CONSTRAINT FK_B1DE937CBF396750 FOREIGN KEY (id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "order" ADD CONSTRAINT FK_F5299398B7970CF8 FOREIGN KEY (artist_id) REFERENCES artist (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE order_item ADD CONSTRAINT FK_52EA1F098D9F6D38 FOREIGN KEY (order_id) REFERENCES "order" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE order_item ADD CONSTRAINT FK_52EA1F0973694F FOREIGN KEY (beat_id) REFERENCES beat (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE wallet ADD CONSTRAINT FK_7C68921FA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE wallet_transaction ADD CONSTRAINT FK_7DAF972712520F3 FOREIGN KEY (wallet_id) REFERENCES wallet (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE admin DROP CONSTRAINT FK_880E0D76BF396750');
        $this->addSql('ALTER TABLE artist DROP CONSTRAINT FK_1599687BF396750');
        $this->addSql('ALTER TABLE beat DROP CONSTRAINT FK_D5F9069CAD72BF');
        $this->addSql('ALTER TABLE beatmaker DROP CONSTRAINT FK_B1DE937CBF396750');
        $this->addSql('ALTER TABLE "order" DROP CONSTRAINT FK_F5299398B7970CF8');
        $this->addSql('ALTER TABLE order_item DROP CONSTRAINT FK_52EA1F098D9F6D38');
        $this->addSql('ALTER TABLE order_item DROP CONSTRAINT FK_52EA1F0973694F');
        $this->addSql('ALTER TABLE wallet DROP CONSTRAINT FK_7C68921FA76ED395');
        $this->addSql('ALTER TABLE wallet_transaction DROP CONSTRAINT FK_7DAF972712520F3');
        $this->addSql('DROP TABLE admin');
        $this->addSql('DROP TABLE artist');
        $this->addSql('DROP TABLE beat');
        $this->addSql('DROP TABLE beatmaker');
        $this->addSql('DROP TABLE "order"');
        $this->addSql('DROP TABLE order_item');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE wallet');
        $this->addSql('DROP TABLE wallet_transaction');
    }
}
