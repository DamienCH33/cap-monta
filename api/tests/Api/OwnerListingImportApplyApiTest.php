<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Accommodation;
use App\Entity\District;
use App\Entity\ListingImport;
use App\Entity\PricePeriod;
use App\Entity\Unavailability;
use App\Entity\User;
use App\Enum\AccommodationStatus;
use App\Enum\AccommodationType;
use App\Enum\Resort;
use App\Enum\UnavailabilitySource;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Lot 4c: what the owner kept on the check screen is written in one go, into a new draft or one
 * of his accommodations. All or nothing, row by row refusals, a reading used once.
 */
final class OwnerListingImportApplyApiTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private User $alice;
    /** Next year: every date below is in the future whatever the day the tests run. */
    private int $year;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $this->em = $em;
        (new ORMPurger($this->em))->purge();

        $this->year = (int) date('Y') + 1;
        $this->alice = $this->owner('alice@example.com');
        $this->em->persist(new District('la-lande', 'La Lande', Resort::Chm));
        $this->em->flush();
    }

    public function testTheCheckScreenGetsRowsReadyToSave(): void
    {
        $import = $this->readImport($this->alice);
        $this->client->loginUser($this->alice, 'main');

        $this->client->request('GET', '/api/owner/listing-imports/'.$import->getId()->toRfc4122());

        $proposal = $this->json()['proposal'];
        self::assertSame('la-lande', $proposal['listing']['district']);
        self::assertSame(65000, $proposal['periods'][0]['weeklyPrice']);
        self::assertTrue($proposal['periods'][1]['unitToConfirm']);
        self::assertSame([], $proposal['contacts']);
        self::assertNull($this->json()['appliedTo']);
    }

    public function testANewDraftIsCreatedWithItsRatesAndTakenDates(): void
    {
        $import = $this->readImport($this->alice);
        $this->client->loginUser($this->alice, 'main');

        $this->apply($import, [
            'accommodation' => $this->form(),
            'periods' => [$this->rate('07-03', '07-31', weekly: 65000, minimum: 7, saturday: true), $this->rate('09-01', '10-01', weekly: 42000)],
            'unavailable' => [['start' => "{$this->year}-07-10", 'end' => "{$this->year}-07-17"], ['start' => "{$this->year}-07-17", 'end' => "{$this->year}-07-24"]],
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertTrue($this->json()['created']);
        $accommodation = $this->accommodation($this->json()['slug']);
        self::assertSame(AccommodationStatus::Draft, $accommodation->getStatus());
        self::assertSame('la-lande', $accommodation->getDistrict()?->getSlug());
        self::assertSame(['television'], $accommodation->getAmenities());
        self::assertSame([[65000, 7, true], [42000, 1, false]], array_map(
            static fn (PricePeriod $p): array => [$p->getWeeklyPrice(), $p->getMinimumNights(), $p->prefersSaturdayArrival()],
            $this->em->getRepository(PricePeriod::class)->findBy(['accommodation' => $accommodation], ['startDate' => 'ASC']),
        ));
        self::assertSame([["{$this->year}-07-10", "{$this->year}-07-24", UnavailabilitySource::Import]], $this->taken($accommodation), 'merged into one stretch');

        $this->apply($import, ['accommodation' => $this->form(), 'periods' => [], 'unavailable' => []]);

        self::assertResponseStatusCodeSame(409, 'a reading is used once: no second draft on a double click');
        self::assertSame($accommodation->getSlug(), $this->json()['slug']);
    }

    public function testOneWrongRowAndNothingIsWritten(): void
    {
        $import = $this->readImport($this->alice);
        $this->client->loginUser($this->alice, 'main');

        $this->apply($import, [
            'accommodation' => [...$this->form(), 'type' => null],
            'periods' => [
                $this->rate('07-03', '07-31', weekly: 65000),
                $this->rate('07-10', '07-03', weekly: 65000),
                $this->rate('07-24', '08-07', weekly: 70000),
            ],
            'unavailable' => [],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(
            ['accommodation.type', 'periods[1].end', 'periods[2].start'],
            array_column($this->json()['violations'], 'propertyPath'),
        );
        self::assertSame(0, $this->em->getRepository(Accommodation::class)->count([]));
    }

    public function testAnExistingAccommodationKeepsWhatItAlreadyHas(): void
    {
        $home = new Accommodation('bungalow-alice', Resort::Euronat, AccommodationType::Bungalow, 6, 3, 'Une description.', $this->alice);
        $this->em->persist($home);
        $existing = new PricePeriod($home, new \DateTimeImmutable("{$this->year}-08-01"), new \DateTimeImmutable("{$this->year}-09-01"));
        $existing->setWeeklyPrice(80000);
        $this->em->persist($existing);
        $this->em->persist(new Unavailability($home, new \DateTimeImmutable("{$this->year}-07-10"), new \DateTimeImmutable("{$this->year}-07-17"), UnavailabilitySource::Block));
        $import = $this->readImport($this->alice);
        $this->client->loginUser($this->alice, 'main');

        $this->apply($import, ['slug' => 'bungalow-alice', 'periods' => [$this->rate('08-15', '09-15', weekly: 70000)], 'unavailable' => []]);

        self::assertResponseStatusCodeSame(422, 'an overlap with his own rates is his to settle');
        self::assertSame('periods[0].start', $this->json()['violations'][0]['propertyPath']);

        $this->apply($import, [
            'slug' => 'bungalow-alice',
            'periods' => [$this->rate('09-01', '10-01', weekly: 42000)],
            'unavailable' => [['start' => "{$this->year}-07-03", 'end' => "{$this->year}-07-31"]],
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertFalse($this->json()['created']);
        $this->em->clear();
        self::assertSame([
            ["{$this->year}-07-03", "{$this->year}-07-10", UnavailabilitySource::Import],
            ["{$this->year}-07-10", "{$this->year}-07-17", UnavailabilitySource::Block],
            ["{$this->year}-07-17", "{$this->year}-07-31", UnavailabilitySource::Import],
        ], $this->taken($this->accommodation('bungalow-alice')), 'only the free days are added around his own block');
    }

    public function testSomeoneElsesImportOrAccommodationIsNotFound(): void
    {
        $bob = $this->owner('bob@example.com');
        $bobsHome = new Accommodation('bungalow-bob', Resort::Euronat, AccommodationType::Bungalow, 4, 2, 'Une description.', $bob);
        $this->em->persist($bobsHome);
        $bobsImport = $this->readImport($bob);
        $import = $this->readImport($this->alice);
        $this->client->loginUser($this->alice, 'main');

        $this->apply($bobsImport, ['accommodation' => $this->form(), 'periods' => [], 'unavailable' => []]);
        self::assertResponseStatusCodeSame(404);

        $this->apply($import, ['slug' => 'bungalow-bob', 'periods' => [], 'unavailable' => []]);
        self::assertResponseStatusCodeSame(404);
    }

    public function testAReadingStillRunningCannotBeSaved(): void
    {
        $import = new ListingImport($this->alice, 'Bungalow, juillet 650 € la semaine.', new \DateTimeImmutable());
        $this->em->persist($import);
        $this->em->flush();
        $this->client->loginUser($this->alice, 'main');

        $this->apply($import, ['accommodation' => $this->form(), 'periods' => [], 'unavailable' => []]);

        self::assertResponseStatusCodeSame(409);
    }

    private function readImport(User $owner): ListingImport
    {
        $import = new ListingImport($owner, 'Mobil-home La Lande. Juillet 650 € la semaine. Septembre 420 €.', new \DateTimeImmutable());
        $import->succeed([
            'periods' => [
                ['label' => 'juillet', 'start' => "{$this->year}-07-01", 'end' => "{$this->year}-08-01", 'prices' => [['amount' => 650, 'unit' => 'week']], 'minimumNights' => null, 'saturdayArrival' => false],
                ['label' => 'septembre', 'start' => "{$this->year}-09-01", 'end' => "{$this->year}-10-01", 'prices' => [['amount' => 420, 'unit' => 'unknown']], 'minimumNights' => null, 'saturdayArrival' => false],
            ],
            'unavailable' => [],
            'questions' => ['420 € pour « septembre » : est-ce le prix de la semaine, de la nuit ou du séjour ?'],
            'listing' => ['type' => 'mobile_home', 'capacity' => null, 'bedrooms' => null, 'surface' => null, 'district' => 'La Lande', 'amenities' => [], 'petsPolicy' => null, 'otherFeatures' => []],
            'contacts' => [],
        ], new \DateTimeImmutable());
        $this->em->persist($import);
        $this->em->flush();

        return $import;
    }

    /**
     * @return array<string, mixed>
     */
    private function form(): array
    {
        return [
            'resort' => 'chm', 'type' => 'mobile_home', 'capacity' => 4, 'bedrooms' => 2, 'surface' => null,
            'district' => 'la-lande', 'amenities' => ['television'], 'petsPolicy' => 'not_allowed',
            'description' => 'Mobil-home au calme, quartier La Lande.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rate(string $start, string $end, ?int $weekly = null, int $minimum = 1, bool $saturday = false): array
    {
        return [
            'start' => "{$this->year}-{$start}", 'end' => "{$this->year}-{$end}",
            'weeklyPrice' => $weekly, 'nightlyPrice' => null, 'minimumNights' => $minimum, 'saturdayArrival' => $saturday,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function apply(ListingImport $import, array $payload): void
    {
        $this->client->request(
            'POST',
            '/api/owner/listing-imports/'.$import->getId()->toRfc4122().'/apply',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );
    }

    private function accommodation(string $slug): Accommodation
    {
        $accommodation = $this->em->getRepository(Accommodation::class)->findOneBy(['slug' => $slug]);
        self::assertInstanceOf(Accommodation::class, $accommodation);

        return $accommodation;
    }

    /**
     * @return list<array{0: string, 1: string, 2: UnavailabilitySource}>
     */
    private function taken(Accommodation $accommodation): array
    {
        return array_map(
            static fn (Unavailability $u): array => [$u->getStartDate()->format('Y-m-d'), $u->getEndDate()->format('Y-m-d'), $u->getSource()],
            $this->em->getRepository(Unavailability::class)->findBy(['accommodation' => $accommodation], ['startDate' => 'ASC']),
        );
    }

    private function owner(string $email): User
    {
        $owner = new User($email, 'Propriétaire');
        $owner->verifyEmail(new \DateTimeImmutable());
        $this->em->persist($owner);

        return $owner;
    }

    /** @return array<string, mixed> */
    private function json(): array
    {
        $data = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }
}
