<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260922122614 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Signalements d’annonces (listing_report).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE listing_report (id UUID NOT NULL, reason VARCHAR(255) NOT NULL, message TEXT DEFAULT NULL, reporter_email VARCHAR(180) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, handled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, accommodation_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_20D756578F3692CD ON listing_report (accommodation_id)');
        $this->addSql('ALTER TABLE listing_report ADD CONSTRAINT FK_20D756578F3692CD FOREIGN KEY (accommodation_id) REFERENCES accommodation (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE listing_report DROP CONSTRAINT FK_20D756578F3692CD');
        $this->addSql('DROP TABLE listing_report');
    }
}
