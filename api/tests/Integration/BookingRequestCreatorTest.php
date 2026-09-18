<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Accommodation;
use App\Entity\PricePeriod;
use App\Entity\Unavailability;
use App\Entity\User;
use App\Enum\AccommodationType;
use App\Enum\BookingRefusalReason;
use App\Enum\BookingRequestStatus;
use App\Enum\Resort;
use App\Enum\UnavailabilitySource;
use App\Service\Booking\BookingRefusedException;
use App\Service\Booking\BookingRequestCreator;
use App\Service\Booking\NewBookingRequest;
use App\Tests\DatabaseTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class BookingRequestCreatorTest extends DatabaseTestCase
{
    private EntityManagerInterface $em;
    private BookingRequestCreator $creator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->creator = self::getContainer()->get(BookingRequestCreator::class);
    }

    public function testItCreatesAPendingRequestWithAFrozenPrice(): void
    {
        $accommodation = $this->createAccommodation('mobile-home-pins');
        $this->addPricePeriod($accommodation, '2026-07-01', '2026-08-01', weekly: 40000);

        $request = $this->creator->create($accommodation, $this->input('2026-07-01', '2026-07-08'));

        self::assertSame(BookingRequestStatus::Pending, $request->getStatus());
        self::assertSame(40000, $request->getEstimatedPrice());
        self::assertSame(7, $request->nights());
        self::assertEquals(
            $request->getCreatedAt()->modify('+48 hours'),
            $request->getExpiresAt(),
        );
    }

    public function testItAcceptsAStayWithoutAnyPublishedRate(): void
    {
        $accommodation = $this->createAccommodation('bungalow-sans-tarif');

        $request = $this->creator->create($accommodation, $this->input('2026-07-01', '2026-07-08'));

        self::assertNull($request->getEstimatedPrice());
    }

    public function testItRefusesDatesAlreadyTaken(): void
    {
        $accommodation = $this->createAccommodation('mobile-home-occupe');
        $this->em->persist(new Unavailability(
            $accommodation,
            new \DateTimeImmutable('2026-08-10'),
            new \DateTimeImmutable('2026-08-15'),
            UnavailabilitySource::Booking,
        ));
        $this->em->flush();

        $this->assertRefusal(
            BookingRefusalReason::Unavailable,
            fn () => $this->creator->create($accommodation, $this->input('2026-08-12', '2026-08-14')),
        );
    }

    public function testItRefusesMoreGuestsThanTheAccommodationSleeps(): void
    {
        $accommodation = $this->createAccommodation('caravane-petite', maxCapacity: 4);

        $this->assertRefusal(
            BookingRefusalReason::TooManyGuests,
            fn () => $this->creator->create($accommodation, $this->input('2026-07-01', '2026-07-08', adults: 5)),
        );
    }

    public function testItRefusesAStayShorterThanTheOwnerMinimum(): void
    {
        $accommodation = $this->createAccommodation('bungalow-minimum');
        $this->addPricePeriod($accommodation, '2026-07-01', '2026-08-01', nightly: 7000, minimumNights: 3);

        $this->assertRefusal(
            BookingRefusalReason::StayTooShort,
            fn () => $this->creator->create($accommodation, $this->input('2026-07-01', '2026-07-03')),
        );
    }

    /**
     * Asserts the request is refused, and refused for the expected rule.
     */
    private function assertRefusal(BookingRefusalReason $reason, callable $action): void
    {
        try {
            $action();
        } catch (BookingRefusedException $exception) {
            self::assertSame($reason, $exception->getReason());

            return;
        }

        self::fail(sprintf('Expected the request to be refused for %s.', $reason->value));
    }

    private function input(
        string $arrival,
        string $departure,
        int $adults = 2,
    ): NewBookingRequest {
        return new NewBookingRequest(
            new \DateTimeImmutable($arrival),
            new \DateTimeImmutable($departure),
            $adults,
            'Damien Chauveau',
            'damien@example.com',
        );
    }

    private function createAccommodation(string $slug, int $maxCapacity = 6): Accommodation
    {
        $owner = new User($slug.'@example.com', 'Proprietaire test');
        $this->em->persist($owner);

        $accommodation = new Accommodation($slug, Resort::Chm, AccommodationType::MobileHome, 4, 2, 'Test accommodation', $owner);
        $accommodation->setMaxCapacity($maxCapacity);

        $this->em->persist($accommodation);
        $this->em->flush();

        return $accommodation;
    }

    private function addPricePeriod(
        Accommodation $accommodation,
        string $start,
        string $end,
        ?int $weekly = null,
        ?int $nightly = null,
        int $minimumNights = 1,
    ): void {
        $period = new PricePeriod(
            $accommodation,
            new \DateTimeImmutable($start),
            new \DateTimeImmutable($end),
            $minimumNights,
        );
        $period->setWeeklyPrice($weekly);
        $period->setNightlyPrice($nightly);

        $this->em->persist($period);
        $this->em->flush();
    }
}
