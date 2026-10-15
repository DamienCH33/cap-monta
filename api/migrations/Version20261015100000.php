<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Date-times with their time zone (TIMESTAMP WITH TIME ZONE).
 *
 * The values already stored were written by PHP without offset, in the time zone set by
 * Kernel::boot (Europe/Paris, since 22/09/2026; production opened on 28/09/2026): they are
 * read as Paris time. The DATE columns (stays, rates) do not move, nor Messenger's table
 * (written in UTC by Messenger, its schema is Messenger's).
 *
 * Each table is rewritten once, under an exclusive lock: a few milliseconds at this size.
 */
final class Version20261015100000 extends AbstractMigration
{
    private const ZONE = 'Europe/Paris';

    /** @var array<string, list<string>> */
    private const COLUMNS = [
        'accommodation' => ['created_at', 'updated_at', 'calendar_checked_at'],
        'booking_request' => ['created_at', 'expires_at', 'responded_at'],
        'booking_tracking_link' => ['created_at'],
        'listing_import' => ['created_at', 'finished_at', 'applied_at'],
        'listing_report' => ['created_at', 'handled_at'],
        'photo' => ['created_at'],
        'price_period' => ['created_at'],
        'unavailability' => ['created_at'],
        '"user"' => ['created_at', 'email_verified_at'],
    ];

    public function getDescription(): string
    {
        return 'Horodatages en TIMESTAMP WITH TIME ZONE (valeurs existantes lues à l’heure de Paris)';
    }

    public function up(Schema $schema): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            $this->addSql(sprintf('ALTER TABLE %s %s', $table, implode(', ', array_map(
                static fn (string $column): string => sprintf("ALTER %1\$s TYPE TIMESTAMP(0) WITH TIME ZONE USING %1\$s AT TIME ZONE '%2\$s'", $column, self::ZONE),
                $columns,
            ))));
        }
    }

    public function down(Schema $schema): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            $this->addSql(sprintf('ALTER TABLE %s %s', $table, implode(', ', array_map(
                static fn (string $column): string => sprintf("ALTER %1\$s TYPE TIMESTAMP(0) WITHOUT TIME ZONE USING %1\$s AT TIME ZONE '%2\$s'", $column, self::ZONE),
                $columns,
            ))));
        }
    }
}
