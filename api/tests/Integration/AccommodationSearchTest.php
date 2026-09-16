<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Accommodation;
use App\Enum\Resort;
use App\Factory\AccommodationFactory;
use App\Factory\UnavailabilityFactory;
use App\Repository\AccommodationRepository;
use App\Tests\DatabaseTestCase;

final class AccommodationSearchTest extends DatabaseTestCase
{
    private AccommodationRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();
        $this->repository = self::getContainer()->get(AccommodationRepository::class);
    }

    public function testAnAccommodationBookedDuringTheStayIsNotReturned(): void
    {
        $this->bookMidAugust(AccommodationFactory::createOne());

        self::assertSame([], $this->search('2026-08-12', '2026-08-14'));
    }

    public function testAnAccommodationFreeFromTheCheckoutDayIsReturned(): void
    {
        $this->bookMidAugust(AccommodationFactory::createOne(['slug' => 'le-mobil-home']));

        self::assertSame(['le-mobil-home'], $this->search('2026-08-15', '2026-08-20'));
    }

    public function testAStayEndingOnTheFirstBookedDayIsReturned(): void
    {
        $this->bookMidAugust(AccommodationFactory::createOne(['slug' => 'le-bungalow']));

        self::assertSame(['le-bungalow'], $this->search('2026-08-08', '2026-08-10'));
    }

    public function testAnAccommodationTooSmallIsNotReturned(): void
    {
        AccommodationFactory::createOne(['capacity' => 4, 'maxCapacity' => 4]);

        self::assertSame([], $this->search('2026-08-10', '2026-08-15', guests: 6));
    }

    public function testTheResortFilterOnlyKeepsItsOwnAccommodations(): void
    {
        AccommodationFactory::createOne(['slug' => 'chez-nous', 'resort' => Resort::Chm]);
        AccommodationFactory::createOne(['slug' => 'chez-eux', 'resort' => Resort::Euronat]);

        self::assertSame(
            ['chez-nous'],
            $this->search('2026-08-10', '2026-08-15', resort: Resort::Chm),
        );
    }

    /**
     * Du 10 au 15 août : la plage de référence de tous les tests de bornes.
     */
    private function bookMidAugust(Accommodation $accommodation): void
    {
        UnavailabilityFactory::createOne([
            'accommodation' => $accommodation,
            'startDate' => new \DateTimeImmutable('2026-08-10'),
            'endDate' => new \DateTimeImmutable('2026-08-15'),
        ]);
    }

    /**
     * @return list<string> les slugs trouvés, dans l'ordre renvoyé par la requête
     */
    private function search(
        string $arrival,
        string $departure,
        int $guests = 2,
        ?Resort $resort = null,
    ): array {
        return array_map(
            static fn (Accommodation $accommodation): string => $accommodation->getSlug(),
            $this->repository->searchAvailable(
                new \DateTimeImmutable($arrival),
                new \DateTimeImmutable($departure),
                $guests,
                $resort,
            ),
        );
    }
}
