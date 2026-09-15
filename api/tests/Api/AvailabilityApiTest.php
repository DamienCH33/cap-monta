<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Accommodation;
use App\Entity\Unavailability;
use App\Enum\AccommodationType;
use App\Enum\Resort;
use App\Enum\UnavailabilitySource;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AvailabilityApiTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testItReturnsThePeriodsTakenInTheTwelveMonthsAhead(): void
    {
        $accommodation = $this->createAccommodation('mobile-home-pins');

        // Anchored on today: a hard-coded date would silently stop testing
        // anything once it falls behind the window.
        $start = (new \DateTimeImmutable('today'))->modify('+2 months');
        $end = $start->modify('+5 days');
        $this->block($accommodation, $start, $end);

        $payload = $this->request('/api/accommodations/mobile-home-pins/availability');

        self::assertResponseIsSuccessful();
        self::assertSame('mobile-home-pins', $payload['slug'] ?? null);

        $busy = $payload['busy'] ?? [];
        self::assertCount(1, $busy);
        self::assertStringStartsWith($start->format('Y-m-d'), (string) ($busy[0]['start'] ?? ''));
        self::assertStringStartsWith($end->format('Y-m-d'), (string) ($busy[0]['end'] ?? ''));
        self::assertSame('block', $busy[0]['source'] ?? null);
    }

    public function testAnAccommodationWithNothingBookedHasAnEmptyCalendar(): void
    {
        $this->createAccommodation('bungalow-dunes');

        $payload = $this->request('/api/accommodations/bungalow-dunes/availability');

        self::assertResponseIsSuccessful();
        self::assertSame([], $payload['busy'] ?? null);
    }

    public function testAPastBookingIsNotReturned(): void
    {
        $accommodation = $this->createAccommodation('caravane-ocean');

        $start = (new \DateTimeImmutable('today'))->modify('-3 months');
        $this->block($accommodation, $start, $start->modify('+5 days'));

        $payload = $this->request('/api/accommodations/caravane-ocean/availability');

        self::assertResponseIsSuccessful();
        self::assertSame([], $payload['busy'] ?? null);
    }

    public function testAnUnknownAccommodationIsNotFound(): void
    {
        $this->request('/api/accommodations/nope/availability');

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @return array<string, mixed>
     */
    private function request(string $uri): array
    {
        $this->client->request('GET', $uri, server: ['HTTP_ACCEPT' => 'application/ld+json']);

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

    private function block(Accommodation $accommodation, \DateTimeImmutable $start, \DateTimeImmutable $end): void
    {
        $this->em->persist(new Unavailability($accommodation, $start, $end, UnavailabilitySource::Block));
        $this->em->flush();
    }
}
