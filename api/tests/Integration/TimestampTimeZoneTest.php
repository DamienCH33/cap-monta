<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\BookingRequest;
use App\Factory\BookingRequestFactory;
use App\Tests\DatabaseTestCase;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

final class TimestampTimeZoneTest extends DatabaseTestCase
{
    public function testEveryDateTimeColumnKeepsItsTimeZone(): void
    {
        $db = self::getContainer()->get(Connection::class);

        $naive = $db->fetchFirstColumn(
            <<<'SQL'
                SELECT table_name || '.' || column_name FROM information_schema.columns
                WHERE table_schema = 'public' AND data_type = 'timestamp without time zone'
                  AND table_name NOT IN ('messenger_messages', 'doctrine_migration_versions')
                SQL,
        );

        self::assertSame([], $naive, 'Messenger and Doctrine Migrations write their own tables, in UTC.');
    }

    public function testTheDatabaseTalksInTheTimeZoneOfTheSite(): void
    {
        $db = self::getContainer()->get(Connection::class);

        self::assertSame('Europe/Paris', $db->fetchOne('SHOW TIME ZONE'));
    }

    public function testADateTimeComesBackAsTheSameInstantInLocalTime(): void
    {
        $request = BookingRequestFactory::createOne();
        $created = $request->getCreatedAt();
        $expires = $request->getExpiresAt();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $reloaded = $em->find(BookingRequest::class, $request->getId()) ?? self::fail();

        self::assertSame($created->getTimestamp(), $reloaded->getCreatedAt()->getTimestamp());
        self::assertSame($expires->getTimestamp(), $reloaded->getExpiresAt()->getTimestamp());
        self::assertSame($created->format(\DATE_ATOM), $reloaded->getCreatedAt()->format(\DATE_ATOM), 'Paris offset, as before.');
    }
}
