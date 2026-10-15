<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Tracking keys stored hashed (SHA-256, hex): booking_request.tracking_token keeps the hash of
 * the key sent at creation, booking_tracking_link the hashes of the keys sent later.
 *
 * The keys already sent are hashed in place: the links in the guests' mailboxes and browsers
 * keep working (the API hashes the key it receives and looks the hash up).
 */
final class Version20261015090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Jetons de suivi des demandes stockés hachés (SHA-256), liens envoyés après la création';
    }

    public function up(Schema $schema): void
    {
        // A longer varchar: no table rewrite in PostgreSQL. The new values (64 characters)
        // cannot collide with the old ones (48) during the update: the unique index holds.
        $this->addSql('ALTER TABLE booking_request ALTER tracking_token TYPE VARCHAR(64)');
        $this->addSql("UPDATE booking_request SET tracking_token = encode(sha256(convert_to(tracking_token, 'UTF8')), 'hex') WHERE length(tracking_token) = 48");

        $this->addSql('CREATE TABLE booking_tracking_link (id UUID NOT NULL, token_hash VARCHAR(64) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, booking_request_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6FBA4BF4B3BC57DA ON booking_tracking_link (token_hash)');
        $this->addSql('CREATE INDEX IDX_6FBA4BF4DBE4DBD4 ON booking_tracking_link (booking_request_id)');
        $this->addSql('ALTER TABLE booking_tracking_link ADD CONSTRAINT FK_6FBA4BF4DBE4DBD4 FOREIGN KEY (booking_request_id) REFERENCES booking_request (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // A hash cannot be turned back into the key. The column gets back its shape, filled
        // with values nobody holds: every tracking link already sent stops working.
        $this->write('<comment>Retour arrière : tous les liens de suivi déjà envoyés aux voyageurs cessent de fonctionner.</comment>');

        $this->addSql('DROP TABLE booking_tracking_link');
        $this->addSql('UPDATE booking_request SET tracking_token = substr(tracking_token, 1, 48) WHERE length(tracking_token) > 48');
        $this->addSql('ALTER TABLE booking_request ALTER tracking_token TYPE VARCHAR(48)');
    }
}
