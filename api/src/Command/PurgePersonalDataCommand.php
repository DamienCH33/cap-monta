<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Personal data is kept as long as it serves, then goes (GDPR, storage limitation). The
 * durations are the ones written in the privacy policy (ADR 032); change both together.
 *
 * Meant for a daily cron job in production. Harmless to run often: each step only touches
 * what is past its deadline and not yet purged.
 */
#[AsCommand(name: 'app:privacy:purge', description: 'Efface les données personnelles arrivées au bout de leur durée de conservation')]
final readonly class PurgePersonalDataCommand
{
    /** A finished stay: the owner may still need the guest's details for a dispute, a deposit. */
    public const string STAY_RETENTION = '1 year';
    /** A request that never became a stay (declined, expired, withdrawn). */
    public const string UNUSED_REQUEST_RETENTION = '6 months';
    /** The text pasted into the import assistant, often with a phone number or an email. */
    public const string IMPORT_TEXT_RETENTION = '30 days';
    /** A report and the address of the person who sent it. */
    public const string REPORT_RETENTION = '1 year';
    /** An account whose address was never confirmed, with nothing published. */
    public const string UNVERIFIED_ACCOUNT_RETENTION = '7 days';
    /** Emails that could not be sent (Messenger's « failed » queue holds their content). */
    public const string FAILED_MESSAGE_RETENTION = '30 days';

    public const string ANONYMOUS_DOMAIN = 'anonyme.invalid';

    public function __construct(
        private Connection $db,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $now = $this->clock->now();
        $today = $now->setTime(0, 0);
        $ago = static fn (\DateTimeImmutable $from, string $duration): string => $from->modify('-'.$duration)->format('Y-m-d H:i:s');

        // Dates, amounts and status stay: the owner's history and the statistics keep working.
        // The tracking link dies with the rest (new random token).
        $requests = $this->db->executeStatement(
            <<<'SQL'
                UPDATE booking_request
                SET guest_name = 'Voyageur',
                    guest_email = 'x-' || id || '@' || :domain,
                    guest_phone = NULL,
                    message = NULL,
                    owner_message = NULL,
                    tracking_token = substr(md5(random()::text || id::text) || md5(random()::text), 1, 48)
                WHERE guest_email NOT LIKE '%@' || :domain
                  AND (end_date < :stay_end
                       OR (status IN ('declined', 'expired', 'cancelled') AND created_at < :unused_since))
                SQL,
            [
                'domain' => self::ANONYMOUS_DOMAIN,
                'stay_end' => $ago($today, self::STAY_RETENTION),
                'unused_since' => $ago($now, self::UNUSED_REQUEST_RETENTION),
            ],
        );

        // The extraction result keeps the fields read (no contact: ContactDetector masked them);
        // the pasted text itself goes. The hash stays, so the same text is still recognised.
        $imports = $this->db->executeStatement(
            "UPDATE listing_import SET source_text = '', last_error = NULL WHERE source_text <> '' AND created_at < :since",
            ['since' => $ago($now, self::IMPORT_TEXT_RETENTION)],
        );

        $reports = $this->db->executeStatement(
            'DELETE FROM listing_report WHERE created_at < :since',
            ['since' => $ago($now, self::REPORT_RETENTION)],
        );

        $accounts = $this->db->executeStatement(
            <<<'SQL'
                DELETE FROM "user" u
                WHERE u.email_verified_at IS NULL
                  AND u.created_at < :since
                  AND NOT EXISTS (SELECT 1 FROM accommodation a WHERE a.owner_id = u.id)
                SQL,
            ['since' => $ago($now, self::UNVERIFIED_ACCOUNT_RETENTION)],
        );

        $messages = $this->db->executeStatement(
            "DELETE FROM messenger_messages WHERE queue_name = 'failed' AND created_at < :since",
            ['since' => $ago($now, self::FAILED_MESSAGE_RETENTION)],
        );

        $io->success(sprintf(
            '%d demande(s) anonymisée(s), %d texte(s) d’import effacé(s), %d signalement(s), %d compte(s) jamais confirmé(s), %d email(s) en échec supprimé(s).',
            $requests,
            $imports,
            $reports,
            $accounts,
            $messages,
        ));

        return 0;
    }
}
