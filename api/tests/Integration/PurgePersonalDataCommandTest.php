<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Enum\BookingRequestStatus;
use App\Factory\AccommodationFactory;
use App\Factory\BookingRequestFactory;
use App\Factory\UserFactory;
use App\Tests\DatabaseTestCase;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class PurgePersonalDataCommandTest extends DatabaseTestCase
{
    public function testOldPersonalDataGoesRecentDataStays(): void
    {
        $accommodation = AccommodationFactory::createOne();

        $oldStay = BookingRequestFactory::createOne([
            'accommodation' => $accommodation,
            'startDate' => new \DateTimeImmutable('-2 years'),
            'endDate' => new \DateTimeImmutable('-2 years +7 days'),
            'guestName' => 'Anna de Vries',
            'guestEmail' => 'anna@example.com',
            'status' => BookingRequestStatus::Accepted,
        ]);
        $oldRefusal = BookingRequestFactory::createOne([
            'accommodation' => $accommodation,
            'guestName' => 'Jan Peeters',
            'guestEmail' => 'jan@example.com',
            'status' => BookingRequestStatus::Declined,
        ]);
        $current = BookingRequestFactory::createOne([
            'accommodation' => $accommodation,
            'guestName' => 'Lea Martin',
            'guestEmail' => 'lea@example.com',
        ]);

        $forgotten = UserFactory::createOne(['email' => 'jamais-confirme@example.com']);
        $recent = UserFactory::createOne(['email' => 'inscrit-hier@example.com']);

        $db = self::getContainer()->get(Connection::class);
        $db->executeStatement("UPDATE booking_request SET created_at = now() - interval '7 months' WHERE id = ?", [$oldRefusal->getId()->toRfc4122()]);
        $db->executeStatement("UPDATE \"user\" SET created_at = now() - interval '8 days' WHERE id = ?", [$forgotten->getId()->toRfc4122()]);
        $tokenBefore = $oldStay->getTrackingToken();

        $tester = new CommandTester(new Application(self::$kernel)->find('app:privacy:purge'));
        self::assertSame(0, $tester->execute([]));

        $row = static fn (string $id): array => $db->fetchAssociative('SELECT * FROM booking_request WHERE id = ?', [$id]) ?: [];

        $stay = $row($oldStay->getId()->toRfc4122());
        self::assertSame('Voyageur', $stay['guest_name']);
        self::assertStringEndsWith('@anonyme.invalid', $stay['guest_email']);
        self::assertNull($stay['guest_phone']);
        self::assertNotSame($tokenBefore, $stay['tracking_token'], 'the old tracking link must stop working');
        self::assertSame('accepted', $stay['status'], 'the owner keeps his history');

        self::assertSame('Voyageur', $row($oldRefusal->getId()->toRfc4122())['guest_name']);
        self::assertSame('Lea Martin', $row($current->getId()->toRfc4122())['guest_name']);

        $emails = $db->fetchFirstColumn('SELECT email FROM "user"');
        self::assertNotContains('jamais-confirme@example.com', $emails);
        self::assertContains('inscrit-hier@example.com', $emails);
        unset($recent);

        // A second run changes nothing.
        $tester->execute([]);
        self::assertStringContainsString('0 demande(s) anonymisée(s)', preg_replace('/\s+/', ' ', $tester->getDisplay()) ?? '');
    }
}
