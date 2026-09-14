<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260914114404 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE price_period (id UUID NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, weekly_price INT DEFAULT NULL, nightly_price INT DEFAULT NULL, minimum_nights SMALLINT DEFAULT 1 NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, accommodation_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_8821B69E8F3692CD ON price_period (accommodation_id)');
        $this->addSql('ALTER TABLE price_period ADD CONSTRAINT FK_8821B69E8F3692CD FOREIGN KEY (accommodation_id) REFERENCES accommodation (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql(<<<'SQL'
            ALTER TABLE price_period
                ADD CONSTRAINT price_period_dates_order
                CHECK (end_date > start_date)
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE price_period
                ADD CONSTRAINT price_period_has_a_price
                CHECK (weekly_price IS NOT NULL OR nightly_price IS NOT NULL)
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE price_period
                ADD CONSTRAINT price_period_no_overlap
                EXCLUDE USING gist (
                    accommodation_id WITH =,
                    daterange(start_date, end_date, '[)') WITH &&
                )
            SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE price_period DROP CONSTRAINT FK_8821B69E8F3692CD');
        $this->addSql('DROP TABLE price_period');
    }
}
