<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Accommodation;
use App\Entity\BookingRequest;
use App\Entity\Photo;
use App\Entity\PricePeriod;
use App\Entity\User;
use App\Enum\AccommodationType;
use App\Enum\Resort;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class OwnerRatesApiTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private User $alice;
    private Accommodation $home;
    /** Next year: every date below is in the future whatever the day the tests run. */
    private int $year;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $this->em = $em;
        (new ORMPurger($this->em))->purge();

        $this->year = (int) date('Y') + 1;
        $this->alice = new User('alice@example.com', 'Alice');
        $this->alice->verifyEmail(new \DateTimeImmutable());
        $this->em->persist($this->alice);
        $this->home = new Accommodation('bungalow-alice', Resort::Euronat, AccommodationType::Bungalow, 6, 3, 'Test accommodation with a description long enough to be published.', $this->alice);
        $this->home->addPhoto(new Photo($this->home, 1600, 1066));
        $this->home->publish();
        $this->em->persist($this->home);
        $this->em->flush();
    }

    public function testAnAnonymousVisitorCannotReadTheRates(): void
    {
        $this->client->request('GET', '/api/owner/accommodations/bungalow-alice/rates');

        self::assertResponseStatusCodeSame(401);
    }

    public function testThePublicSeesARateAsSoonAsTheOwnerAddsIt(): void
    {
        $this->login();

        $this->send('POST', '', $this->period('07-03', '08-28', weekly: 90000, minimum: 7, saturday: true));

        self::assertResponseStatusCodeSame(201);
        $period = $this->json()['periods'][0];
        self::assertSame(90000, $period['weeklyPrice']);
        self::assertNull($period['nightlyPrice']);
        self::assertTrue($period['saturdayArrival']);

        $this->client->request('GET', '/api/accommodations/bungalow-alice');
        $rates = $this->json()['pricePeriods'];
        self::assertCount(1, $rates);
        self::assertTrue($rates[0]['saturdayArrival']);
    }

    public function testInvalidRatesAreRefusedWithTheFieldToFix(): void
    {
        $this->login();

        $cases = [
            'weeklyPrice' => $this->period('07-03', '07-10'),                              // no price at all
            'nightlyPrice' => $this->period('07-03', '07-10', weekly: 50000, nightly: 50),  // under 1 €
            'end' => $this->period('07-10', '07-03', weekly: 50000),                       // end before start
            'minimumNights' => $this->period('07-03', '07-10', weekly: 50000, minimum: 0),
        ];

        foreach ($cases as $field => $body) {
            $this->send('POST', '', $body);

            self::assertResponseStatusCodeSame(422, $field);
            self::assertSame($field, $this->json()['violations'][0]['propertyPath']);
        }
    }

    public function testTwoPeriodsCannotOverlapButMayTouch(): void
    {
        $this->login();
        $this->send('POST', '', $this->period('07-03', '07-10', weekly: 50000));

        $this->send('POST', '', $this->period('07-09', '07-17', weekly: 60000));
        self::assertResponseStatusCodeSame(409);

        $this->send('POST', '', $this->period('07-10', '07-17', weekly: 60000));
        self::assertResponseStatusCodeSame(201);
    }

    public function testAPeriodIsEditedAndDeleted(): void
    {
        $this->login();
        $this->send('POST', '', $this->period('07-03', '07-10', weekly: 50000));
        $id = $this->json()['periods'][0]['id'];

        // Editing it does not collide with itself.
        $this->send('PUT', '/'.$id, $this->period('07-03', '07-17', weekly: 55000, nightly: 9000));
        self::assertResponseIsSuccessful();
        self::assertSame($this->year.'-07-17', $this->json()['periods'][0]['end']);
        self::assertSame(9000, $this->json()['periods'][0]['nightlyPrice']);

        $this->client->request('DELETE', '/api/owner/accommodations/bungalow-alice/rates/'.$id);
        self::assertResponseIsSuccessful();
        self::assertSame([], $this->json()['periods']);
    }

    public function testLastYearsRatesAreCopiedOnTheSameWeekdays(): void
    {
        $this->login();
        $this->send('POST', '', $this->period('07-03', '07-10', weekly: 50000, saturday: true));
        $this->send('POST', '', $this->period('07-10', '08-28', weekly: 80000, minimum: 7));

        self::assertSame(['from' => $this->year, 'to' => $this->year + 1], $this->json()['copySuggestion']);

        $this->send('POST', '/copy', ['fromYear' => $this->year]);

        self::assertResponseIsSuccessful();
        self::assertSame(2, $this->json()['copied']);
        $copies = array_slice($this->json()['periods'], 2);
        self::assertCount(2, $copies);

        foreach ($copies as $index => $copy) {
            $original = $this->json()['periods'][$index];
            self::assertSame(
                (new \DateTimeImmutable($original['start']))->format('N'),
                (new \DateTimeImmutable($copy['start']))->format('N'),
                'A Saturday stays a Saturday.',
            );
            self::assertSame($original['weeklyPrice'], $copy['weeklyPrice']);
        }
        self::assertTrue($copies[0]['saturdayArrival']);

        // Next year is covered and the one after is beyond the horizon: nothing more to offer.
        self::assertNull($this->json()['copySuggestion']);

        // Copying again changes nothing: every copy would overlap.
        $this->send('POST', '/copy', ['fromYear' => $this->year]);
        self::assertSame(0, $this->json()['copied']);
        self::assertSame(2, $this->json()['skipped']);
    }

    public function testSomeoneElsesRatesAreNotFound(): void
    {
        $bob = new User('bob@example.com', 'Bob');
        $this->em->persist($bob);
        $this->em->flush();
        $this->client->loginUser($bob, 'main');

        $this->client->request('GET', '/api/owner/accommodations/bungalow-alice/rates');

        self::assertResponseStatusCodeSame(404);
    }

    public function testAnArrivalOutsideTheSaturdayPreferenceIsSentButFlagged(): void
    {
        $period = new PricePeriod($this->home, new \DateTimeImmutable($this->year.'-07-03'), new \DateTimeImmutable($this->year.'-08-28'));
        $period->setWeeklyPrice(80000)->setNightlyPrice(12000)->setSaturdayArrival(true);
        $this->em->persist($period);
        $this->em->flush();

        // A Tuesday arrival: allowed, but the quote says it and the request is flagged.
        $tuesday = new \DateTimeImmutable('tuesday '.$this->year.'-07-06');
        $this->client->request('GET', sprintf(
            '/api/accommodations/bungalow-alice/quote?arrival=%s&departure=%s&guests=2',
            $tuesday->format('Y-m-d'),
            $tuesday->modify('+2 days')->format('Y-m-d'),
        ));
        self::assertTrue($this->json()['available']);
        self::assertTrue($this->json()['outsideRules']);

        $this->client->request('POST', '/api/booking-requests', server: ['CONTENT_TYPE' => 'application/ld+json'], content: json_encode([
            'accommodationSlug' => 'bungalow-alice',
            'arrival' => $tuesday->format('Y-m-d'),
            'departure' => $tuesday->modify('+2 days')->format('Y-m-d'),
            'adults' => 2,
            'guestName' => 'Jeanne Martin',
            'guestEmail' => 'jeanne@example.com',
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);

        $this->em->clear();
        $request = $this->em->getRepository(BookingRequest::class)->findOneBy([]);
        self::assertNotNull($request);
        self::assertTrue($request->isOutsideRules());
    }

    private function login(): void
    {
        $this->client->loginUser($this->alice, 'main');
    }

    /**
     * @return array<string, mixed>
     */
    private function period(string $start, string $end, ?int $weekly = null, ?int $nightly = null, int $minimum = 1, bool $saturday = false): array
    {
        return [
            'start' => $this->year.'-'.$start,
            'end' => $this->year.'-'.$end,
            'weeklyPrice' => $weekly,
            'nightlyPrice' => $nightly,
            'minimumNights' => $minimum,
            'saturdayArrival' => $saturday,
        ];
    }

    /**
     * @param array<string, mixed> $body
     */
    private function send(string $method, string $path, array $body): void
    {
        $this->client->request(
            $method,
            '/api/owner/accommodations/bungalow-alice/rates'.$path,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($body, \JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @return array<mixed>
     */
    private function json(): array
    {
        $data = json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }
}
