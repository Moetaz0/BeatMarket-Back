<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260107120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add stripe_customer_id to users table';
    }

    public function up(Schema $schema): void
    {
        // PostgreSQL
        $this->addSql('ALTER TABLE users ADD stripe_customer_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E99F105B75 ON users (stripe_customer_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_1483A5E99F105B75');
        $this->addSql('ALTER TABLE users DROP stripe_customer_id');
    }
}
