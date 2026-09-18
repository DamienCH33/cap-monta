<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Enum\AccommodationType;
use App\Factory\AccommodationFactory;
use App\Factory\DistrictFactory;
use App\Factory\UnavailabilityFactory;
use App\Tests\ApiTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class StaySuggestionTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        // Le client d'abord : Foundry démarre le noyau dès la première factory,
        // et createClient() refuse un noyau déjà démarré.
        $this->client = static::createClient();
        static::mockTime('2026-07-01 10:00:00');
    }

    public function testSuggestsNearestShiftsFirstAndNeverTheRequestedStay(): void
    {
        AccommodationFactory::createOne();

        self::assertSame(
            [
                ['2026-07-12', '2026-07-19', 1],
                ['2026-07-14', '2026-07-21', 1],
                ['2026-07-11', '2026-07-18', 1],
            ],
            $this->suggest(['arrival' => '2026-07-13', 'departure' => '2026-07-20']),
        );
    }

    public function testNeverSuggestsAnArrivalInThePast(): void
    {
        AccommodationFactory::createOne();

        // Arrivée aujourd'hui : tous les décalages négatifs tombent dans le passé.
        self::assertSame(
            ['2026-07-02', '2026-07-03', '2026-07-04'],
            array_column($this->suggest(['arrival' => '2026-07-01', 'departure' => '2026-07-03']), 0),
        );
    }

    public function testArrivalOnTheLastDayOfAnUnavailabilityIsFree(): void
    {
        $blocked = AccommodationFactory::createOne();
        AccommodationFactory::createOne();
        UnavailabilityFactory::week($blocked, 2); // 13/07 → 20/07

        // +1 donne une arrivée le 20 : le jour où l'indisponibilité se termine.
        self::assertSame(
            [
                ['2026-07-18', '2026-07-25', 1],
                ['2026-07-20', '2026-07-27', 2],
                ['2026-07-17', '2026-07-24', 1],
            ],
            $this->suggest(['arrival' => '2026-07-19', 'departure' => '2026-07-26']),
        );
    }

    public function testDepartureOnTheFirstDayOfAnUnavailabilityIsFree(): void
    {
        $blocked = AccommodationFactory::createOne();
        AccommodationFactory::createOne();
        UnavailabilityFactory::week($blocked, 2); // 13/07 → 20/07

        // -1 donne un départ le 13 : le jour où l'indisponibilité commence.
        self::assertSame(
            [
                ['2026-07-06', '2026-07-13', 2],
                ['2026-07-08', '2026-07-15', 1],
                ['2026-07-05', '2026-07-12', 2],
            ],
            $this->suggest(['arrival' => '2026-07-07', 'departure' => '2026-07-14']),
        );
    }

    public function testIgnoresAccommodationsTooSmallForTheGroup(): void
    {
        AccommodationFactory::createOne(['capacity' => 4, 'maxCapacity' => 4]);
        AccommodationFactory::createOne(['capacity' => 8, 'maxCapacity' => 8]);

        self::assertSame(
            [1, 1, 1],
            array_column($this->suggest(['arrival' => '2026-07-13', 'departure' => '2026-07-20', 'guests' => 6]), 2),
        );
    }

    public function testFiltersByDistrict(): void
    {
        // Les logements reçoivent un objet District…
        AccommodationFactory::createOne(['district' => DistrictFactory::named('Europa')]);
        AccommodationFactory::createOne(['district' => DistrictFactory::named('Médoc')]);

        // … la requête HTTP, elle, transporte du texte : le slug ou le nom.
        self::assertSame(
            [1, 1, 1],
            array_column($this->suggest(['arrival' => '2026-07-13', 'departure' => '2026-07-20', 'district' => 'Europa']), 2),
        );
    }

    public function testAppliesTheSearchFiltersToTheSuggestedStays(): void
    {
        AccommodationFactory::createOne(['type' => AccommodationType::MobileHome, 'bedrooms' => 3]);
        AccommodationFactory::createOne(['type' => AccommodationType::Caravan, 'bedrooms' => 1]);

        // Sans filtre, les deux logements sont libres sur chacun des trois créneaux.
        self::assertSame(
            [2, 2, 2],
            array_column($this->suggest(['arrival' => '2026-07-13', 'departure' => '2026-07-20']), 2),
        );

        // Avec les filtres de la recherche, la caravane sort du décompte.
        self::assertSame(
            [1, 1, 1],
            array_column($this->suggest([
                'arrival' => '2026-07-13',
                'departure' => '2026-07-20',
                'type' => 'mobile_home',
                'bedrooms' => 3,
            ]), 2),
        );
    }

    public function testReturnsAnEmptyListWhenNothingIsFree(): void
    {
        $accommodation = AccommodationFactory::createOne();
        foreach (range(0, 4) as $offset) {
            UnavailabilityFactory::week($accommodation, $offset); // 29/06 → 03/08, sans trou
        }

        self::assertSame([], $this->suggest(['arrival' => '2026-07-13', 'departure' => '2026-07-20']));
    }

    public function testRejectsDepartureBeforeArrival(): void
    {
        $this->client->request('GET', '/api/stay-suggestions?arrival=2026-07-20&departure=2026-07-13');

        self::assertResponseStatusCodeSame(400);
    }

    public function testRequiresBothDates(): void
    {
        $this->client->request('GET', '/api/stay-suggestions');

        self::assertResponseStatusCodeSame(422);
    }

    /**
     * @param array<string, string|int> $query
     *
     * @return list<array{0: string, 1: string, 2: int}>
     */
    private function suggest(array $query): array
    {
        $this->client->request('GET', '/api/stay-suggestions?'.http_build_query($query), server: [
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        self::assertResponseIsSuccessful();

        /** @var array{member: list<array{arrival: string, departure: string, availableCount: int}>} $body */
        $body = json_decode((string) $this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

        return array_map(
            static fn (array $s): array => [$s['arrival'], $s['departure'], $s['availableCount']],
            $body['member'],
        );
    }
}
