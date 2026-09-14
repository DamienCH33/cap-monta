<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Accommodation;
use App\Entity\Unavailability;
use App\Enum\AccommodationType;
use App\Enum\Resort;
use App\Enum\UnavailabilitySource;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class UnavailabilityOverlapTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testCheckoutAndCheckinOnTheSameDayAreAllowed(): void
    {
        $accommodation = $this->createAccommodation('bungalow-des-dunes');

        $this->em->persist(new Unavailability(
            $accommodation,
            new \DateTimeImmutable('2026-08-10'),
            new \DateTimeImmutable('2026-08-15'),
            UnavailabilitySource::Booking,
        ));
        $this->em->persist(new Unavailability(
            $accommodation,
            new \DateTimeImmutable('2026-08-15'),
            new \DateTimeImmutable('2026-08-20'),
            UnavailabilitySource::Booking,
        ));
        $this->em->flush();

        self::assertSame(2, $this->em->getRepository(Unavailability::class)->count(['accommodation' => $accommodation]));
    }

    public function testSameDatesOnAnotherAccommodationAreAllowed(): void
    {
        $first = $this->createAccommodation('caravane-du-lac');
        $second = $this->createAccommodation('caravane-des-pins');

        $this->em->persist(new Unavailability(
            $first,
            new \DateTimeImmutable('2026-08-10'),
            new \DateTimeImmutable('2026-08-15'),
            UnavailabilitySource::Booking,
        ));
        $this->em->persist(new Unavailability(
            $second,
            new \DateTimeImmutable('2026-08-10'),
            new \DateTimeImmutable('2026-08-15'),
            UnavailabilitySource::Booking,
        ));
        $this->em->flush();

        self::assertSame(2, $this->em->getRepository(Unavailability::class)->count([]));
    }

    private function createAccommodation(string $slug): Accommodation
    {
        $accommodation = new Accommodation($slug, Resort::Chm, AccommodationType::MobileHome, 4, 2, 'Test accommodation');
        $this->em->persist($accommodation);
        $this->em->flush();

        return $accommodation;
    }
}
