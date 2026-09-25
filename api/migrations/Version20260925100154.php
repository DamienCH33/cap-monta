<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Conditions of the stay and fees on top of the rent (StayTerms, embedded in accommodation).
 * The DROP INDEX …_no_overlap lines Doctrine proposes were removed (ADR 010).
 */
final class Version20260925100154 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Conditions du séjour et frais en plus du loyer';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE accommodation ADD terms_check_in_from VARCHAR(5) DEFAULT NULL');
        $this->addSql('ALTER TABLE accommodation ADD terms_check_out_before VARCHAR(5) DEFAULT NULL');
        $this->addSql('ALTER TABLE accommodation ADD terms_deposit_percent SMALLINT DEFAULT NULL');
        $this->addSql('ALTER TABLE accommodation ADD terms_security_deposit INT DEFAULT NULL');
        $this->addSql('ALTER TABLE accommodation ADD terms_cancellation_policy TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE accommodation ADD terms_cleaning_fee INT DEFAULT NULL');
        $this->addSql('ALTER TABLE accommodation ADD terms_linen_fee INT DEFAULT NULL');
        $this->addSql('ALTER TABLE accommodation ADD terms_tourist_tax INT DEFAULT NULL');
        $this->addSql('ALTER TABLE accommodation ADD terms_resort_fee INT DEFAULT NULL');
        $this->addSql('ALTER TABLE accommodation ADD CONSTRAINT accommodation_terms_deposit_percent CHECK (terms_deposit_percent BETWEEN 0 AND 100)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE accommodation DROP CONSTRAINT accommodation_terms_deposit_percent');
        $this->addSql('ALTER TABLE accommodation DROP terms_check_in_from');
        $this->addSql('ALTER TABLE accommodation DROP terms_check_out_before');
        $this->addSql('ALTER TABLE accommodation DROP terms_deposit_percent');
        $this->addSql('ALTER TABLE accommodation DROP terms_security_deposit');
        $this->addSql('ALTER TABLE accommodation DROP terms_cancellation_policy');
        $this->addSql('ALTER TABLE accommodation DROP terms_cleaning_fee');
        $this->addSql('ALTER TABLE accommodation DROP terms_linen_fee');
        $this->addSql('ALTER TABLE accommodation DROP terms_tourist_tax');
        $this->addSql('ALTER TABLE accommodation DROP terms_resort_fee');
    }
}
