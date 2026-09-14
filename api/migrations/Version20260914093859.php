<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260914093859 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Accommodations and unavailabilities: exclusion constraint preventing overlapping stays';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE accommodation (id UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, slug VARCHAR(255) NOT NULL, resort VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, district VARCHAR(255) DEFAULT NULL, capacity SMALLINT NOT NULL, max_capacity SMALLINT NOT NULL, bedrooms SMALLINT NOT NULL, surface SMALLINT DEFAULT NULL, amenities JSON NOT NULL, description TEXT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2D385412989D9B62 ON accommodation (slug)');
        $this->addSql('CREATE TABLE unavailability (id UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, source VARCHAR(255) NOT NULL, external_uid VARCHAR(255) DEFAULT NULL, accommodation_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_unavailability_lookup ON unavailability (accommodation_id, start_date)');
        $this->addSql('CREATE INDEX IDX_F0016D18F3692CD ON unavailability (accommodation_id)');
        $this->addSql('ALTER TABLE unavailability ADD CONSTRAINT FK_F0016D18F3692CD FOREIGN KEY (accommodation_id) REFERENCES accommodation (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('CREATE EXTENSION IF NOT EXISTS btree_gist');

        $this->addSql(<<<'SQL'
            ALTER TABLE unavailability
                ADD CONSTRAINT unavailability_dates_order
                CHECK (end_date > start_date)
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE unavailability
                ADD CONSTRAINT unavailability_no_overlap
                EXCLUDE USING gist (
                    accommodation_id WITH =,
                    daterange(start_date, end_date, '[)') WITH &&
                )
            SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE unavailability DROP CONSTRAINT FK_F0016D18F3692CD');
        $this->addSql('DROP TABLE accommodation');
        $this->addSql('DROP TABLE unavailability');
    }
}
