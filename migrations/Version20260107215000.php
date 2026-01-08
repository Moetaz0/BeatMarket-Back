<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260107215000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed Standard, Exclusive, and Premium license types';
    }

    public function up(Schema $schema): void
    {
        // Insert the two license types
        $this->addSql("INSERT INTO license (name, terms, price_multiplier, is_exclusive) VALUES ('Standard', 'Use the beat in non-exclusive projects with others', 1.0, false)");
        $this->addSql("INSERT INTO license (name, terms, price_multiplier, is_exclusive) VALUES ('Premium', 'Own the beat completely - you are the sole owner', 2.0, true)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM license WHERE name IN ('Standard', 'Premium')");
    }
}
