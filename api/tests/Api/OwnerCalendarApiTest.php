<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Accommodation;
use App\Entity\Photo;
use App\Entity\Unavailability;
use App\Entity\User;
use App\Enum\AccommodationType;
use App\Enum\Resort;
use App\Enum\UnavailabilitySource;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class OwnerCalendarApiTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private \DateTimeImmutable $today;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $this->em = $em;

        (new ORMPurger($this->em))->purge();

        // Anchored on today: the API refuses past dates.
        $this->today = new \DateTimeImmutable('today');
    }

    public function testAnAnonymousVisitorCannotReadACalendar(): void
    {
        $this->accommodation('bungalow-alice', $this->owner('alice@example.com'));

        $this->client->request('GET', '/api/owner/accommodations/bungalow-alice/calendar');

        self::assertResponseStatusCodeSame(401);
    }

    public function testTheOwnerSeesWhereEachPeriodComesFrom(): void
    {
        $home = $this->accommodation('bungalow-alice', $this->loggedInOwner());
        $this->unavailability($home, 10, 17, UnavailabilitySource::Booking);
        $this->unavailability($home, 17, 20, UnavailabilitySource::Block, 'famille');

        $this->client->request('GET', '/api/owner/accommodations/bungalow-alice/calendar');
        $periods = $this->json()['periods'];

        self::assertResponseIsSuccessful();
        self::assertSame(['booking', 'block'], array_column($periods, 'source'));
        self::assertSame([null, 'famille'], array_column($periods, 'note'));
        self::assertSame([false, true], array_column($periods, 'removable'));
    }

    public function testABlockIsAddedAndShowsOnThePublicCalendarAtOnce(): void
    {
        $this->published('bungalow-alice', $this->loggedInOwner());

        // First read: the public calendar is now in the Redis cache, still empty.
        self::assertSame([], $this->publicBusy('bungalow-alice'));

        $this->postBlock('bungalow-alice', $this->day(3), $this->day(5), 'réparations');

        self::assertResponseStatusCodeSame(201);
        self::assertTrue($this->json()['upToDate']);
        // The cache has been invalidated: the new block is visible, without its note or source.
        self::assertSame([[
            'start' => $this->day(3),
            'end' => $this->day(5),
        ]], array_map(
            static fn (array $period): array => ['start' => substr($period['start'], 0, 10), 'end' => substr($period['end'], 0, 10)],
            $this->publicBusy('bungalow-alice'),
        ));
    }

    public function testASingleNightCanBeBlocked(): void
    {
        $this->accommodation('bungalow-alice', $this->loggedInOwner());

        $this->postBlock('bungalow-alice', $this->day(8), $this->day(9));

        self::assertResponseStatusCodeSame(201);
        self::assertCount(1, $this->json()['periods']);
    }

    public function testABlockCannotOverlapAnotherPeriod(): void
    {
        $home = $this->accommodation('bungalow-alice', $this->loggedInOwner());
        $this->unavailability($home, 10, 17, UnavailabilitySource::Booking);

        $this->postBlock('bungalow-alice', $this->day(15), $this->day(20));

        self::assertResponseStatusCodeSame(409);
    }

    public function testABlockMayStartOnTheDayABookingEnds(): void
    {
        $home = $this->accommodation('bungalow-alice', $this->loggedInOwner());
        $this->unavailability($home, 10, 17, UnavailabilitySource::Booking);

        $this->postBlock('bungalow-alice', $this->day(17), $this->day(20));

        self::assertResponseStatusCodeSame(201);
    }

    public function testInvalidDatesAreRefused(): void
    {
        $this->accommodation('bungalow-alice', $this->loggedInOwner());

        $cases = [
            'start' => [$this->day(-3), $this->day(2)],        // in the past
            'end' => [$this->day(5), $this->day(5)],           // empty range
            'end ' => [$this->day(5), '2026-02-30'],           // no such day
            'end  ' => [$this->day(5), $this->day(600)],       // beyond 18 months
        ];

        foreach ($cases as $field => [$start, $end]) {
            $this->postBlock('bungalow-alice', $start, $end);

            self::assertResponseStatusCodeSame(422, $start.' → '.$end);
            self::assertSame(trim($field), $this->json()['violations'][0]['propertyPath']);
        }

        self::assertSame(0, $this->em->getRepository(Unavailability::class)->count([]));
    }

    public function testTheOwnerRemovesHisBlockButNeverABooking(): void
    {
        $home = $this->accommodation('bungalow-alice', $this->loggedInOwner());
        $booking = $this->unavailability($home, 10, 17, UnavailabilitySource::Booking);
        $block = $this->unavailability($home, 20, 25, UnavailabilitySource::Block);

        $this->client->request('DELETE', '/api/owner/accommodations/bungalow-alice/calendar/blocks/'.$booking->getId()->toRfc4122());
        self::assertResponseStatusCodeSame(409);

        $this->client->request('DELETE', '/api/owner/accommodations/bungalow-alice/calendar/blocks/'.$block->getId()->toRfc4122());
        self::assertResponseIsSuccessful();
        self::assertSame(['booking'], array_column($this->json()['periods'], 'source'));
    }

    public function testSomeoneElsesCalendarIsNotFound(): void
    {
        $this->loggedInOwner();
        $bobs = $this->accommodation('bungalow-bob', $this->owner('bob@example.com'));
        $block = $this->unavailability($bobs, 20, 25, UnavailabilitySource::Block);

        $this->client->request('GET', '/api/owner/accommodations/bungalow-bob/calendar');
        self::assertResponseStatusCodeSame(404);

        $this->client->request('DELETE', '/api/owner/accommodations/bungalow-bob/calendar/blocks/'.$block->getId()->toRfc4122());
        self::assertResponseStatusCodeSame(404);
    }

    public function testConfirmingTheCalendarLightsUpTheBadge(): void
    {
        $this->published('bungalow-alice', $this->loggedInOwner());

        self::assertFalse($this->publicAccommodation('bungalow-alice')['calendarUpToDate']);

        $this->client->request('POST', '/api/owner/accommodations/bungalow-alice/calendar/confirm');
        self::assertResponseIsSuccessful();
        self::assertTrue($this->json()['upToDate']);

        self::assertTrue($this->publicAccommodation('bungalow-alice')['calendarUpToDate']);
    }

    public function testTheOwnersListSaysWhichCalendarsNeedChecking(): void
    {
        $alice = $this->loggedInOwner();
        $checked = $this->accommodation('bungalow-verifie', $alice);
        $checked->markCalendarChecked(new \DateTimeImmutable('-3 days'));
        $stale = $this->accommodation('bungalow-oublie', $alice);
        $stale->markCalendarChecked(new \DateTimeImmutable('-40 days'));
        $this->accommodation('bungalow-jamais', $alice);
        $this->em->flush();

        $this->client->request('GET', '/api/owner/accommodations', server: ['HTTP_ACCEPT' => 'application/ld+json']);
        $bySlug = array_column($this->json()['member'], null, 'slug');

        self::assertTrue($bySlug['bungalow-verifie']['calendarUpToDate']);
        self::assertFalse($bySlug['bungalow-oublie']['calendarUpToDate']);
        self::assertNotNull($bySlug['bungalow-oublie']['calendarCheckedAt']);
        self::assertFalse($bySlug['bungalow-jamais']['calendarUpToDate']);
        self::assertNull($bySlug['bungalow-jamais']['calendarCheckedAt']);
    }

    private function day(int $offset): string
    {
        return $this->today->modify(sprintf('%+d days', $offset))->format('Y-m-d');
    }

    private function postBlock(string $slug, string $start, string $end, ?string $note = null): void
    {
        $this->client->request(
            'POST',
            '/api/owner/accommodations/'.$slug.'/calendar/blocks',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['start' => $start, 'end' => $end, 'note' => $note], \JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @return list<array{start: string, end: string}>
     */
    private function publicBusy(string $slug): array
    {
        $this->client->request('GET', '/api/accommodations/'.$slug.'/availability');
        self::assertResponseIsSuccessful();

        /** @var list<array{start: string, end: string}> $busy */
        $busy = $this->json()['busy'];

        return $busy;
    }

    /**
     * @return array<string, mixed>
     */
    private function publicAccommodation(string $slug): array
    {
        $this->client->request('GET', '/api/accommodations/'.$slug);
        self::assertResponseIsSuccessful();

        return $this->json();
    }

    private function loggedInOwner(): User
    {
        $alice = $this->owner('alice@example.com');
        $this->em->flush();
        $this->client->loginUser($alice, 'main');

        return $alice;
    }

    private function owner(string $email): User
    {
        $owner = new User($email, 'Owner test');
        $owner->verifyEmail(new \DateTimeImmutable());
        $this->em->persist($owner);

        return $owner;
    }

    private function accommodation(string $slug, User $owner): Accommodation
    {
        $accommodation = new Accommodation($slug, Resort::Euronat, AccommodationType::Bungalow, 6, 3, 'Test accommodation with a description long enough to be published.', $owner);
        $this->em->persist($accommodation);
        $this->em->flush();

        return $accommodation;
    }

    private function published(string $slug, User $owner): Accommodation
    {
        $accommodation = $this->accommodation($slug, $owner);
        // A published listing needs a photo: an entity is enough, no file is read here.
        $accommodation->addPhoto(new Photo($accommodation, 1600, 1066));
        $accommodation->publish();
        $this->em->flush();

        return $accommodation;
    }

    private function unavailability(Accommodation $accommodation, int $from, int $to, UnavailabilitySource $source, ?string $note = null): Unavailability
    {
        $unavailability = new Unavailability(
            $accommodation,
            $this->today->modify(sprintf('+%d days', $from)),
            $this->today->modify(sprintf('+%d days', $to)),
            $source,
            $note,
        );
        $this->em->persist($unavailability);
        $this->em->flush();

        return $unavailability;
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
