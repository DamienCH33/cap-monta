<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260922091333 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Calendar: last check by the owner (badge) and a private note on blocks';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE accommodation ADD calendar_checked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE unavailability ADD note VARCHAR(200) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE accommodation DROP calendar_checked_at');
        $this->addSql('ALTER TABLE unavailability DROP note');
    }
}
