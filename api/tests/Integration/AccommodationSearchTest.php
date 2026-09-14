<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Accommodation;
use App\Entity\Unavailability;
use App\Enum\AccommodationType;
use App\Enum\Resort;
use App\Enum\UnavailabilitySource;
use App\Repository\AccommodationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AccommodationSearchTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private AccommodationRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->repository = self::getContainer()->get(AccommodationRepository::class);
    }

    public function testAnAccommodationBookedDuringTheStayIsNotReturned(): void
    {
        $accommodation = $this->createAccommodation('mobile-home-pins');
        $this->book($accommodation, '2026-08-10', '2026-08-15');

        $results = $this->repository->searchAvailable(
            new \DateTimeImmutable('2026-08-12'),
            new \DateTimeImmutable('2026-08-14'),
            2,
        );

        self::assertSame([], $results);
    }

    public function testAnAccommodationFreeFromTheCheckoutDayIsReturned(): void
    {
        $accommodation = $this->createAccommodation('mobile-home-dunes');
        $this->book($accommodation, '2026-08-10', '2026-08-15');

        $results = $this->repository->searchAvailable(
            new \DateTimeImmutable('2026-08-15'),
            new \DateTimeImmutable('2026-08-20'),
            2,
        );

        self::assertSame([$accommodation->getId()->toRfc4122()], $this->ids($results));
    }

    public function testAStayEndingOnTheFirstBookedDayIsReturned(): void
    {
        $accommodation = $this->createAccommodation('bungalow-ocean');
        $this->book($accommodation, '2026-08-10', '2026-08-15');

        $results = $this->repository->searchAvailable(
            new \DateTimeImmutable('2026-08-08'),
            new \DateTimeImmutable('2026-08-10'),
            2,
        );

        self::assertSame([$accommodation->getId()->toRfc4122()], $this->ids($results));
    }

    public function testAnAccommodationTooSmallIsNotReturned(): void
    {
        $this->createAccommodation('caravane-lac', maxCapacity: 4);

        $results = $this->repository->searchAvailable(
            new \DateTimeImmutable('2026-08-10'),
            new \DateTimeImmutable('2026-08-15'),
            6,
        );

        self::assertSame([], $results);
    }

    public function testTheResortFilterOnlyKeepsItsOwnAccommodations(): void
    {
        $chm = $this->createAccommodation('bungalow-chm', resort: Resort::Chm);
        $this->createAccommodation('bungalow-euronat', resort: Resort::Euronat);

        $results = $this->repository->searchAvailable(
            new \DateTimeImmutable('2026-08-10'),
            new \DateTimeImmutable('2026-08-15'),
            2,
            Resort::Chm,
        );

        self::assertSame([$chm->getId()->toRfc4122()], $this->ids($results));
    }

    private function createAccommodation(
        string $slug,
        int $maxCapacity = 6,
        Resort $resort = Resort::Chm,
    ): Accommodation {
        $accommodation = new Accommodation($slug, $resort, AccommodationType::MobileHome, 4, 2, 'Test accommodation');
        $accommodation->setMaxCapacity($maxCapacity);

        $this->em->persist($accommodation);
        $this->em->flush();

        return $accommodation;
    }

    private function book(Accommodation $accommodation, string $start, string $end): void
    {
        $this->em->persist(new Unavailability(
            $accommodation,
            new \DateTimeImmutable($start),
            new \DateTimeImmutable($end),
            UnavailabilitySource::Booking,
        ));
        $this->em->flush();
    }

    /**
     * @param list<Accommodation> $accommodations
     *
     * @return list<string>
     */
    private function ids(array $accommodations): array
    {
        return array_map(
            static fn (Accommodation $accommodation): string => $accommodation->getId()->toRfc4122(),
            $accommodations,
        );
    }
}
