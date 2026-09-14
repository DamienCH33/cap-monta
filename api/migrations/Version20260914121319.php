<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260914121319 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Booking requests: a guest intent, not a reservation — no exclusion constraint here';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE booking_request (id UUID NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, adults SMALLINT NOT NULL, children SMALLINT DEFAULT 0 NOT NULL, guest_name VARCHAR(255) NOT NULL, guest_email VARCHAR(255) NOT NULL, guest_phone VARCHAR(30) DEFAULT NULL, message TEXT DEFAULT NULL, status VARCHAR(255) NOT NULL, estimated_price INT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, accommodation_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_booking_request_pending ON booking_request (status, expires_at)');
        $this->addSql('CREATE INDEX IDX_6129CABF8F3692CD ON booking_request (accommodation_id)');
        $this->addSql('ALTER TABLE booking_request ADD CONSTRAINT FK_6129CABF8F3692CD FOREIGN KEY (accommodation_id) REFERENCES accommodation (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql(<<<'SQL'
            ALTER TABLE booking_request
                ADD CONSTRAINT booking_request_dates_order
                CHECK (end_date > start_date)
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE booking_request
                ADD CONSTRAINT booking_request_has_an_adult
                CHECK (adults >= 1)
            SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE booking_request DROP CONSTRAINT FK_6129CABF8F3692CD');
        $this->addSql('DROP TABLE booking_request');
    }
}
