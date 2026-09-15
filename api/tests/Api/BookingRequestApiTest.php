<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Accommodation;
use App\Entity\PricePeriod;
use App\Entity\Unavailability;
use App\Enum\AccommodationType;
use App\Enum\Resort;
use App\Enum\UnavailabilitySource;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class BookingRequestApiTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testAGuestCanSendARequestAndGetsTheEstimatedPrice(): void
    {
        $accommodation = $this->createAccommodation('mobile-home-pins');
        $this->addWeeklyRate($accommodation, '2026-07-01', '2026-08-01', 40000);

        $payload = $this->post([
            'accommodationSlug' => 'mobile-home-pins',
            'arrival' => '2026-07-01',
            'departure' => '2026-07-08',
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
            new \DateTimeImmutable('2026-08-10'),
            new \DateTimeImmutable('2026-08-15'),
            UnavailabilitySource::Booking,
        ));
        $this->em->flush();

        $this->post([
            'accommodationSlug' => 'mobile-home-occupe',
            'arrival' => '2026-08-12',
            'departure' => '2026-08-14',
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
            'arrival' => '2026-07-01',
            'departure' => '2026-07-08',
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
            'arrival' => '2026-07-01',
            'departure' => '2026-07-08',
            'adults' => 2,
            'guestName' => 'Damien Chauveau',
            'guestEmail' => 'damien@example.com',
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testARequestCanBeReadBackById(): void
    {
        $this->createAccommodation('bungalow-ocean');

        $created = $this->post([
            'accommodationSlug' => 'bungalow-ocean',
            'arrival' => '2026-07-01',
            'departure' => '2026-07-08',
            'adults' => 2,
            'guestName' => 'Damien Chauveau',
            'guestEmail' => 'damien@example.com',
        ]);

        $id = $created['id'] ?? '';
        self::assertIsString($id);

        $this->client->request('GET', '/api/booking-requests/'.$id, server: ['HTTP_ACCEPT' => 'application/ld+json']);

        self::assertResponseIsSuccessful();
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

    private function createAccommodation(string $slug): Accommodation
    {
        $accommodation = new Accommodation($slug, Resort::Chm, AccommodationType::MobileHome, 4, 2, 'Test accommodation');
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
}
