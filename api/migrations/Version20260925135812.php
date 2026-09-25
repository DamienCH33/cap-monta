<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Audit of 25/09: the database accepted absurd values (capacity 0, negative prices, unknown
 * statuses…). Only the PHP validation stood in the way, and the import agent and the commands
 * write without going through it. These CHECK constraints make the database refuse them.
 *
 * Also: guest emails in lower case, a unique index against two identical pending requests sent
 * at the same time, and the unavailability index in the order the search reads it.
 *
 * The exclusion constraints' indexes are now declared on the entities (ADR 010): Doctrine stops
 * proposing to drop them, and the plain index it had added on accommodation_id (covered by the
 * gist index) goes. `doctrine:schema:validate` is green again.
 */
final class Version20260925135812 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Garde-fous en base : contraintes CHECK, email des voyageurs en minuscules, demandes en double';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE booking_request SET guest_email = lower(trim(guest_email)) WHERE guest_email <> lower(trim(guest_email))');

        $this->addSql('CREATE INDEX idx_booking_request_guest_email ON booking_request (guest_email)');
        $this->addSql('CREATE UNIQUE INDEX uniq_booking_request_pending ON booking_request (accommodation_id, guest_email, start_date, end_date) WHERE (status = \'pending\')');
        $this->addSql('DROP INDEX idx_unavailability_lookup');
        $this->addSql('CREATE INDEX idx_unavailability_lookup ON unavailability (accommodation_id, end_date, start_date)');
        $this->addSql('DROP INDEX idx_8821b69e8f3692cd');
        $this->addSql('DROP INDEX idx_f0016d18f3692cd');

        $this->addSql(<<<'SQL'
            ALTER TABLE accommodation
              ADD CONSTRAINT accommodation_capacity_positive CHECK (capacity >= 1),
              ADD CONSTRAINT accommodation_max_capacity_gte CHECK (max_capacity >= capacity),
              ADD CONSTRAINT accommodation_bedrooms_non_negative CHECK (bedrooms >= 0),
              ADD CONSTRAINT accommodation_surface_positive CHECK (surface IS NULL OR surface > 0),
              ADD CONSTRAINT accommodation_status_valid CHECK (status IN ('draft', 'published', 'archived')),
              ADD CONSTRAINT accommodation_resort_valid CHECK (resort IN ('chm', 'euronat')),
              ADD CONSTRAINT accommodation_slug_format CHECK (slug ~ '^[a-z0-9]+(-[a-z0-9]+)*$'),
              ADD CONSTRAINT accommodation_terms_times CHECK (
                (terms_check_in_from IS NULL OR terms_check_in_from ~ '^([01][0-9]|2[0-3]):[0-5][0-9]$')
                AND (terms_check_out_before IS NULL OR terms_check_out_before ~ '^([01][0-9]|2[0-3]):[0-5][0-9]$')
              ),
              ADD CONSTRAINT accommodation_terms_amounts CHECK (
                coalesce(terms_security_deposit, 0) >= 0 AND coalesce(terms_cleaning_fee, 0) >= 0
                AND coalesce(terms_linen_fee, 0) >= 0 AND coalesce(terms_tourist_tax, 0) >= 0
                AND coalesce(terms_resort_fee, 0) >= 0
              )
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE price_period
              ADD CONSTRAINT price_period_prices_positive CHECK ((weekly_price IS NULL OR weekly_price > 0) AND (nightly_price IS NULL OR nightly_price > 0)),
              ADD CONSTRAINT price_period_minimum_nights CHECK (minimum_nights BETWEEN 1 AND 90)
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE booking_request
              ADD CONSTRAINT booking_request_people_non_negative CHECK (children >= 0 AND infants >= 0 AND pets >= 0),
              ADD CONSTRAINT booking_request_amounts_non_negative CHECK ((estimated_price IS NULL OR estimated_price >= 0) AND (agreed_price IS NULL OR agreed_price >= 0)),
              ADD CONSTRAINT booking_request_status_valid CHECK (status IN ('pending', 'accepted', 'declined', 'confirmed', 'completed', 'cancelled', 'expired')),
              ADD CONSTRAINT booking_request_email_lower CHECK (guest_email = lower(guest_email) AND guest_email <> '')
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE unavailability
              ADD CONSTRAINT unavailability_source_valid CHECK (source IN ('booking', 'block', 'import', 'ical')),
              ADD CONSTRAINT unavailability_request_means_booking CHECK (booking_request_id IS NULL OR source = 'booking')
            SQL);

        $this->addSql('ALTER TABLE "user" ADD CONSTRAINT user_email_lower CHECK (email = lower(email))');
        $this->addSql('ALTER TABLE photo ADD CONSTRAINT photo_dimensions_positive CHECK (width > 0 AND height > 0 AND position >= 0)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE photo DROP CONSTRAINT photo_dimensions_positive');
        $this->addSql('ALTER TABLE "user" DROP CONSTRAINT user_email_lower');
        $this->addSql('ALTER TABLE unavailability DROP CONSTRAINT unavailability_source_valid, DROP CONSTRAINT unavailability_request_means_booking');
        $this->addSql('ALTER TABLE booking_request DROP CONSTRAINT booking_request_people_non_negative, DROP CONSTRAINT booking_request_amounts_non_negative, DROP CONSTRAINT booking_request_status_valid, DROP CONSTRAINT booking_request_email_lower');
        $this->addSql('ALTER TABLE price_period DROP CONSTRAINT price_period_prices_positive, DROP CONSTRAINT price_period_minimum_nights');
        $this->addSql('ALTER TABLE accommodation DROP CONSTRAINT accommodation_capacity_positive, DROP CONSTRAINT accommodation_max_capacity_gte, DROP CONSTRAINT accommodation_bedrooms_non_negative, DROP CONSTRAINT accommodation_surface_positive, DROP CONSTRAINT accommodation_status_valid, DROP CONSTRAINT accommodation_resort_valid, DROP CONSTRAINT accommodation_slug_format, DROP CONSTRAINT accommodation_terms_times, DROP CONSTRAINT accommodation_terms_amounts');
        $this->addSql('CREATE INDEX idx_f0016d18f3692cd ON unavailability (accommodation_id)');
        $this->addSql('CREATE INDEX idx_8821b69e8f3692cd ON price_period (accommodation_id)');
        $this->addSql('DROP INDEX idx_unavailability_lookup');
        $this->addSql('CREATE INDEX idx_unavailability_lookup ON unavailability (accommodation_id, start_date)');
        $this->addSql('DROP INDEX uniq_booking_request_pending');
        $this->addSql('DROP INDEX idx_booking_request_guest_email');
    }
}
