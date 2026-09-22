<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Accommodation;
use App\Entity\BookingRequest;
use App\Entity\Photo;
use App\Entity\User;
use App\Enum\AccommodationType;
use App\Enum\Resort;
use App\Service\Booking\BookingAnswerRefused;
use App\Service\Booking\BookingDesk;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Two answers read at the same time: the second one written must be refused, never applied
 * over the first (a cancelled request that still blocks the calendar, for instance).
 */
final class BookingDeskConcurrencyTest extends KernelTestCase
{
    public function testAnAnswerBasedOnAStaleReadIsRefused(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        (new ORMPurger($em))->purge();

        $owner = new User('alice@example.com', 'Alice');
        $owner->verifyEmail(new \DateTimeImmutable());
        $home = new Accommodation('bungalow-alice', Resort::Euronat, AccommodationType::Bungalow, 6, 3, 'Test accommodation with a description long enough to be published.', $owner);
        $home->addPhoto(new Photo($home, 1600, 1066));
        $home->publish();
        $arrival = new \DateTimeImmutable('today +30 days');
        $request = new BookingRequest($home, $arrival, $arrival->modify('+7 days'), 2, 'Jeanne', 'jeanne@example.com');
        $em->persist($owner);
        $em->persist($home);
        $em->persist($request);
        $em->flush();

        // Meanwhile, another process answers: the version in the database moves on.
        $em->getConnection()->executeStatement('UPDATE booking_request SET version = version + 1');

        $desk = self::getContainer()->get(BookingDesk::class);
        self::assertInstanceOf(BookingDesk::class, $desk);

        $this->expectException(BookingAnswerRefused::class);
        $this->expectExceptionMessage('vient de changer');

        $desk->decline($request, null);
    }
}
