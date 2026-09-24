<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Lot 4c: the accommodation filled from a listing reading, and when (a reading is used once).
 * The DROP INDEX …_no_overlap lines Doctrine proposes were removed (ADR 010).
 */
final class Version20260924080801 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Import d\'annonce : logement rempli et date d\'utilisation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE listing_import ADD applied_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE listing_import ADD accommodation_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE listing_import ADD CONSTRAINT FK_79B6EFCE8F3692CD FOREIGN KEY (accommodation_id) REFERENCES accommodation (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_79B6EFCE8F3692CD ON listing_import (accommodation_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE listing_import DROP CONSTRAINT FK_79B6EFCE8F3692CD');
        $this->addSql('DROP INDEX IDX_79B6EFCE8F3692CD');
        $this->addSql('ALTER TABLE listing_import DROP applied_at');
        $this->addSql('ALTER TABLE listing_import DROP accommodation_id');
    }
}
