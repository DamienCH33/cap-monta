<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Accommodation;
use App\Entity\Unavailability;
use App\Enum\AccommodationType;
use App\Enum\Resort;
use App\Enum\UnavailabilitySource;
use App\Repository\UnavailabilityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class UnavailabilityCalendarTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private UnavailabilityRepository $unavailabilities;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->unavailabilities = self::getContainer()->get(UnavailabilityRepository::class);
    }

    public function testAnUnavailabilityInsideTheWindowIsReturned(): void
    {
        $accommodation = $this->createAccommodation('mobile-home-pins');
        $this->block($accommodation, '2026-08-10', '2026-08-15');

        $found = $this->unavailabilities->findForPeriod(
            $accommodation,
            new \DateTimeImmutable('2026-08-01'),
            new \DateTimeImmutable('2026-09-01'),
        );

        self::assertCount(1, $found);
    }

    public function testAnUnavailabilityOutsideTheWindowIsIgnored(): void
    {
        $accommodation = $this->createAccommodation('bungalow-dunes');
        $this->block($accommodation, '2026-08-10', '2026-08-15');

        $found = $this->unavailabilities->findForPeriod(
            $accommodation,
            new \DateTimeImmutable('2026-09-01'),
            new \DateTimeImmutable('2026-09-30'),
        );

        self::assertSame([], $found);
    }

    public function testAnUnavailabilityStartingBeforeTheWindowIsReturned(): void
    {
        $accommodation = $this->createAccommodation('caravane-ocean');
        $this->block($accommodation, '2026-08-25', '2026-09-05');

        $found = $this->unavailabilities->findForPeriod(
            $accommodation,
            new \DateTimeImmutable('2026-09-01'),
            new \DateTimeImmutable('2026-09-30'),
        );

        self::assertCount(1, $found);
    }

    private function createAccommodation(string $slug): Accommodation
    {
        $accommodation = new Accommodation($slug, Resort::Chm, AccommodationType::MobileHome, 4, 2, 'Test accommodation');
        $this->em->persist($accommodation);
        $this->em->flush();

        return $accommodation;
    }

    private function block(Accommodation $accommodation, string $start, string $end): void
    {
        $this->em->persist(new Unavailability(
            $accommodation,
            new \DateTimeImmutable($start),
            new \DateTimeImmutable($end),
            UnavailabilitySource::Block,
        ));
        $this->em->flush();
    }
}
