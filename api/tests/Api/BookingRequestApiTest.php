<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Accommodation;
use App\Entity\District;
use App\Entity\Photo;
use App\Entity\PricePeriod;
use App\Entity\Unavailability;
use App\Entity\User;
use App\Enum\AccommodationType;
use App\Enum\Resort;
use App\Enum\UnavailabilitySource;
use App\Tests\ApiTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class BookingRequestApiTest extends ApiTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testAGuestCanSendARequestAndGetsTheEstimatedPrice(): void
    {
        $accommodation = $this->createAccommodation('mobile-home-pins');
        $this->addWeeklyRate($accommodation, '2027-07-01', '2027-08-01', 40000);

        $payload = $this->post([
            'accommodationSlug' => 'mobile-home-pins',
            'arrival' => '2027-07-01',
            'departure' => '2027-07-08',
            'adults' => 2,
            'guestName' => 'Damien Chauveau',
            'guestEmail' => 'damien@example.com',
            'message' => 'Bonjour, est-ce que les draps sont fournis ?',
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertSame('pending', $payload['status'] ?? null);
        self::assertSame(40000, $payload['estimatedPrice'] ?? null);
        self::assertNotNull($payload['id'] ?? null);
    }

    public function testDatesAlreadyTakenAreRefused(): void
    {
        $accommodation = $this->createAccommodation('mobile-home-occupe');
        $this->em->persist(new Unavailability(
            $accommodation,
            new \DateTimeImmutable('2027-08-10'),
            new \DateTimeImmutable('2027-08-15'),
            UnavailabilitySource::Booking,
        ));
        $this->em->flush();

        $this->post([
            'accommodationSlug' => 'mobile-home-occupe',
            'arrival' => '2027-08-12',
            'departure' => '2027-08-14',
            'adults' => 2,
            'guestName' => 'Damien Chauveau',
            'guestEmail' => 'damien@example.com',
        ]);

        self::assertResponseStatusCodeSame(409);
    }

    public function testAnInvalidEmailIsRejectedBeforeTheDomain(): void
    {
        $this->createAccommodation('bungalow-dunes');

        $this->post([
            'accommodationSlug' => 'bungalow-dunes',
            'arrival' => '2027-07-01',
            'departure' => '2027-07-08',
            'adults' => 2,
            'guestName' => 'Damien Chauveau',
            'guestEmail' => 'pas-un-email',
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testAnUnknownAccommodationIsNotFound(): void
    {
        $this->post([
            'accommodationSlug' => 'nope',
            'arrival' => '2027-07-01',
            'departure' => '2027-07-08',
            'adults' => 2,
            'guestName' => 'Damien Chauveau',
            'guestEmail' => 'damien@example.com',
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testTheGuestFollowsHisRequestWithTheTokenNeverWithTheId(): void
    {
        $this->createAccommodation('bungalow-ocean');

        $created = $this->post([
            'accommodationSlug' => 'bungalow-ocean',
            'arrival' => '2027-07-01',
            'departure' => '2027-07-08',
            'adults' => 2,
            'guestName' => 'Damien Chauveau',
            'guestEmail' => 'damien@example.com',
        ]);

        $token = $created['trackingToken'] ?? '';
        self::assertIsString($token);
        self::assertMatchesRegularExpression('/^[0-9a-f]{48}$/', $token);

        // The id is not a secret: it must not open the request and its contact details.
        $this->client->request('GET', '/api/booking-requests/'.($created['id'] ?? ''));
        self::assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/api/booking-requests/track/'.$token);
        self::assertResponseIsSuccessful();
        $tracked = json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('pending', $tracked['status']);
        self::assertTrue($tracked['cancellable']);
        self::assertNull($tracked['ownerContact'], 'The owner\'s contact comes with the acceptance only.');
    }

    public function testTheSameRequestTwiceIsRefusedWithAClearMessage(): void
    {
        $this->createAccommodation('bungalow-ocean');
        $body = [
            'accommodationSlug' => 'bungalow-ocean',
            'arrival' => '2027-07-01',
            'departure' => '2027-07-08',
            'adults' => 2,
            'guestName' => 'Damien Chauveau',
            'guestEmail' => 'damien@example.com',
        ];

        $this->post($body);
        self::assertResponseStatusCodeSame(201);

        $again = $this->post([...$body, 'guestEmail' => 'Damien@Example.com']);
        self::assertResponseStatusCodeSame(409);
        self::assertStringContainsString('déjà envoyé une demande', (string) ($again['detail'] ?? ''));

        // Other dates: a new request, not a duplicate.
        $this->post([...$body, 'arrival' => '2027-07-08', 'departure' => '2027-07-15']);
        self::assertResponseStatusCodeSame(201);
    }

    public function testTheGuestGetsHisLinksBackByEmailWithoutRevealingAnything(): void
    {
        $this->createAccommodation('bungalow-ocean');
        $created = $this->post([
            'accommodationSlug' => 'bungalow-ocean',
            'arrival' => '2027-07-01',
            'departure' => '2027-07-08',
            'adults' => 2,
            'guestName' => 'Damien Chauveau',
            'guestEmail' => 'damien@example.com',
        ]);

        $this->recover('DAMIEN@example.com');
        self::assertResponseStatusCodeSame(202);
        self::assertEmailCount(1);
        $mail = self::getMailerMessage(0) ?? self::fail('No email.');
        self::assertEmailAddressContains($mail, 'To', 'damien@example.com');
        self::assertEmailTextBodyContains($mail, '/demande/'.$created['trackingToken']);

        // Unknown address: exactly the same answer; only that mailbox learns there is nothing.
        $this->recover('personne@example.com');
        self::assertResponseStatusCodeSame(202);
        self::assertSame('{}', (string) $this->client->getResponse()->getContent());
        self::assertEmailTextBodyContains(self::getMailerMessage(0) ?? self::fail(), "Aucune n'est en cours");

        $this->recover('pas-une-adresse');
        self::assertResponseStatusCodeSame(422);
    }

    public function testAnArrivalInThePastIsRefused(): void
    {
        $this->createAccommodation('bungalow-ocean');

        $yesterday = new \DateTimeImmutable('yesterday');
        $body = $this->post([
            'accommodationSlug' => 'bungalow-ocean',
            'arrival' => $yesterday->format('Y-m-d'),
            'departure' => $yesterday->modify('+7 days')->format('Y-m-d'),
            'adults' => 2,
            'guestName' => 'Damien Chauveau',
            'guestEmail' => 'damien@example.com',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('arrival', $body['violations'][0]['propertyPath'] ?? null);
    }

    public function testSendingARequestWarnsBothSidesAndSchedulesTheExpiry(): void
    {
        $this->createAccommodation('bungalow-ocean');

        $this->post([
            'accommodationSlug' => 'bungalow-ocean',
            'arrival' => '2027-07-01',
            'departure' => '2027-07-08',
            'adults' => 2,
            'guestName' => 'Damien Chauveau',
            'guestEmail' => 'damien@example.com',
            'guestPhone' => '06 12 34 56 78',
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertEmailCount(2);

        $toOwner = self::getMailerMessage(0);
        self::assertNotNull($toOwner);
        self::assertEmailAddressContains($toOwner, 'To', 'bungalow-ocean@example.com');
        // Privacy promise of the booking form: no contact details before acceptance.
        self::assertEmailTextBodyNotContains($toOwner, 'damien@example.com');
        self::assertEmailTextBodyNotContains($toOwner, '06 12 34 56 78');

        $toGuest = self::getMailerMessage(1);
        self::assertNotNull($toGuest);
        self::assertEmailAddressContains($toGuest, 'To', 'damien@example.com');
        self::assertEmailTextBodyContains($toGuest, '/demande/');

        $transport = self::getContainer()->get('messenger.transport.async');
        \assert($transport instanceof \Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport);
        $sent = $transport->getSent();
        self::assertCount(2, $sent);
        self::assertInstanceOf(\App\Message\RemindOwnerOfBookingRequest::class, $sent[0]->getMessage());
        self::assertInstanceOf(\App\Message\ExpireBookingRequest::class, $sent[1]->getMessage());
        self::assertNotNull($sent[0]->last(\Symfony\Component\Messenger\Stamp\DelayStamp::class));
        self::assertNotNull($sent[1]->last(\Symfony\Component\Messenger\Stamp\DelayStamp::class));
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function guestPhones(): iterable
    {
        yield 'français' => ['06 12 34 56 78', 201];
        yield 'français international' => ['+33 6 12 34 56 78', 201];
        yield 'néerlandais' => ['+31 6 12345678', 201];
        yield 'allemand' => ['+49 151 23456789', 201];
        yield 'trop court' => ['06 12 34', 422];
        yield 'étranger sans indicatif' => ['0151 23456789', 422];
        yield 'lettres' => ['+31 six', 422];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('guestPhones')]
    public function testForeignGuestsCanLeaveAnInternationalPhoneNumber(string $phone, int $status): void
    {
        $this->createAccommodation('bungalow-ocean');

        $this->post([
            'accommodationSlug' => 'bungalow-ocean',
            'arrival' => '2027-07-01',
            'departure' => '2027-07-08',
            'adults' => 2,
            'guestName' => 'Anna de Vries',
            'guestEmail' => 'anna@example.com',
            'guestPhone' => $phone,
        ]);

        self::assertResponseStatusCodeSame($status);
    }

    public function testAnArrivalTodayIsRefusedTheOwnerNeedsTimeToAnswer(): void
    {
        $this->createAccommodation('bungalow-ocean');

        $today = new \DateTimeImmutable('today');
        $body = $this->post([
            'accommodationSlug' => 'bungalow-ocean',
            'arrival' => $today->format('Y-m-d'),
            'departure' => $today->modify('+3 days')->format('Y-m-d'),
            'adults' => 2,
            'guestName' => 'Damien Chauveau',
            'guestEmail' => 'damien@example.com',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('arrival', $body['violations'][0]['propertyPath'] ?? null);
    }

    public function testARequestForTomorrowExpiresBeforeTheArrivalDay(): void
    {
        $this->createAccommodation('bungalow-ocean');

        $tomorrow = new \DateTimeImmutable('tomorrow');
        $this->post([
            'accommodationSlug' => 'bungalow-ocean',
            'arrival' => $tomorrow->format('Y-m-d'),
            'departure' => $tomorrow->modify('+3 days')->format('Y-m-d'),
            'adults' => 2,
            'guestName' => 'Damien Chauveau',
            'guestEmail' => 'damien@example.com',
        ]);

        self::assertResponseStatusCodeSame(201);
        $request = $this->em->getRepository(\App\Entity\BookingRequest::class)->findOneBy([]);
        self::assertNotNull($request);
        self::assertLessThanOrEqual($tomorrow, $request->getExpiresAt());
        self::assertLessThan($request->getExpiresAt(), $request->remindAt());
    }

    public function testWhatTheGuestTypesCannotAddAButtonToTheOwnersEmail(): void
    {
        $this->createAccommodation('bungalow-ocean');

        $this->post([
            'accommodationSlug' => 'bungalow-ocean',
            'arrival' => '2027-07-01',
            'departure' => '2027-07-08',
            'adults' => 2,
            'guestName' => "Jean\n\nhttps://evil.example/connexion",
            'guestEmail' => 'damien@example.com',
            'message' => "Bonjour.\n\nhttps://evil.example/mon-espace/demandes\n\nPrix : 0 €",
        ]);

        self::assertResponseStatusCodeSame(201);
        $toOwner = self::getMailerMessage(0);
        self::assertInstanceOf(\Symfony\Component\Mime\Email::class, $toOwner);
        $html = (string) $toOwner->getHtmlBody();
        self::assertStringNotContainsString('href="https://evil.example', $html);
        self::assertStringContainsString('https://evil.example/mon-espace/demandes', $html);
    }

    private function recover(string $email): void
    {
        $this->client->request(
            'POST',
            '/api/booking-requests/recover',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => $email], \JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    private function post(array $body): array
    {
        $this->client->request(
            'POST',
            '/api/booking-requests',
            server: [
                'CONTENT_TYPE' => 'application/ld+json',
                'HTTP_ACCEPT' => 'application/ld+json',
            ],
            content: (string) json_encode($body),
        );

        $decoded = json_decode((string) $this->client->getResponse()->getContent(), true);

        return is_array($decoded) ? $decoded : [];
    }

    private ?District $sharedDistrict = null;

    private function testDistrict(): District
    {
        // A CHM accommodation needs a district to be published: one shared, found or created.
        // Kept in a property: several accommodations may be persisted before a single flush,
        // and findOneBy() only sees what is already in the database.
        if (null !== $this->sharedDistrict) {
            return $this->sharedDistrict;
        }

        $district = $this->em->getRepository(District::class)->findOneBy(['slug' => 'test-district']);

        if (null === $district) {
            $district = new District('test-district', 'Test district', Resort::Chm);
            $this->em->persist($district);
        }

        return $this->sharedDistrict = $district;
    }

    private function createAccommodation(string $slug): Accommodation
    {
        $owner = new User($slug.'@example.com', 'Proprietaire test');
        $this->em->persist($owner);

        $owner->verifyEmail(new \DateTimeImmutable());
        $accommodation = new Accommodation($slug, Resort::Chm, AccommodationType::MobileHome, 4, 2, 'Test accommodation with a description long enough to be published.', $owner);
        $accommodation->setDistrict($this->testDistrict());
        // A published listing needs a photo: an entity is enough, no file is read here.
        $accommodation->addPhoto(new Photo($accommodation, 1600, 1066));
        $accommodation->publish();
        $this->em->persist($accommodation);
        $this->em->flush();

        return $accommodation;
    }

    private function addWeeklyRate(Accommodation $accommodation, string $start, string $end, int $weeklyPrice): void
    {
        $period = new PricePeriod($accommodation, new \DateTimeImmutable($start), new \DateTimeImmutable($end));
        $period->setWeeklyPrice($weeklyPrice);

        $this->em->persist($period);
        $this->em->flush();
    }

    public function testKeepsInfantsAndPets(): void
    {
        $this->createAccommodation('mobile-home-famille');

        $payload = $this->post([
            'accommodationSlug' => 'mobile-home-famille',
            'arrival' => '2027-07-01',
            'departure' => '2027-07-08',
            'adults' => 2,
            'children' => 2,
            'infants' => 1,
            'pets' => 2,
            'guestName' => 'Damien Chauveau',
            'guestEmail' => 'damien@example.com',
        ]);

        // 2 adultes + 2 enfants dans un logement 4 places : le bébé et les animaux ne comptent pas.
        self::assertResponseStatusCodeSame(201);
        self::assertSame(1, $payload['infants'] ?? null);
        self::assertSame(2, $payload['pets'] ?? null);
    }

    public function testRejectsTooManyPets(): void
    {
        $this->createAccommodation('mobile-home-chenil');

        $this->post([
            'accommodationSlug' => 'mobile-home-chenil',
            'arrival' => '2027-07-01',
            'departure' => '2027-07-08',
            'adults' => 2,
            'pets' => 6,
            'guestName' => 'Damien Chauveau',
            'guestEmail' => 'damien@example.com',
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testRefusesUnknownFieldsInTheBody(): void
    {
        $this->createAccommodation('mobile-home-strict');

        $payload = $this->post([
            'accommodationSlug' => 'mobile-home-strict',
            'arrival' => '2027-07-01',
            'departure' => '2027-07-08',
            'adults' => 2,
            'guestName' => 'Damien Chauveau',
            'guestEmail' => 'damien@example.com',
            'status' => 'confirmed',
            'adult' => 9,
        ]);

        self::assertResponseStatusCodeSame(400);

        $detail = is_string($payload['detail'] ?? null) ? $payload['detail'] : '';
        self::assertStringContainsString('"status"', $detail);
        self::assertStringContainsString('"adult"', $detail);
    }
}
