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

final class AccommodationApiTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testTheCollectionListsEveryAccommodation(): void
    {
        $this->createAccommodation('mobile-home-pins');
        $this->createAccommodation('bungalow-dunes');

        $slugs = $this->slugsOf('/api/accommodations');

        self::assertResponseIsSuccessful();
        self::assertSame(['bungalow-dunes', 'mobile-home-pins'], $slugs);
    }

    public function testTheCollectionHidesWhatIsBookedForTheRequestedStay(): void
    {
        $booked = $this->createAccommodation('mobile-home-occupe');
        $this->createAccommodation('bungalow-libre');

        $this->em->persist(new Unavailability(
            $booked,
            new \DateTimeImmutable('2026-08-10'),
            new \DateTimeImmutable('2026-08-15'),
            UnavailabilitySource::Booking,
        ));
        $this->em->flush();

        $slugs = $this->slugsOf('/api/accommodations?arrival=2026-08-12&departure=2026-08-14&guests=2');

        self::assertResponseIsSuccessful();
        self::assertSame(['bungalow-libre'], $slugs);
    }

    public function testAStayEndingWhereAnotherStartsStaysAvailable(): void
    {
        $accommodation = $this->createAccommodation('mobile-home-contigu');

        $this->em->persist(new Unavailability(
            $accommodation,
            new \DateTimeImmutable('2026-08-10'),
            new \DateTimeImmutable('2026-08-15'),
            UnavailabilitySource::Booking,
        ));
        $this->em->flush();

        $slugs = $this->slugsOf('/api/accommodations?arrival=2026-08-15&departure=2026-08-20&guests=2');

        self::assertSame(['mobile-home-contigu'], $slugs);
    }

    public function testABadlyFormattedDateIsRefused(): void
    {
        $this->request('/api/accommodations?arrival=15-08-2026&departure=2026-08-20');

        self::assertResponseStatusCodeSame(400);
    }

    public function testAnAccommodationIsReadableBySlug(): void
    {
        $this->createAccommodation('mobile-home-pins');

        $payload = $this->request('/api/accommodations/mobile-home-pins');

        self::assertResponseIsSuccessful();
        self::assertSame('mobile-home-pins', $payload['slug'] ?? null);
        self::assertSame('chm', $payload['resort'] ?? null);
        self::assertArrayNotHasKey('id', $payload);
    }

    public function testAnUnknownSlugIsNotFound(): void
    {
        $this->request('/api/accommodations/nope');

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @return array<string, mixed>
     */
    private function request(string $uri): array
    {
        $this->client->request('GET', $uri, server: ['HTTP_ACCEPT' => 'application/ld+json']);

        $content = (string) $this->client->getResponse()->getContent();
        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return list<string>
     */
    private function slugsOf(string $uri): array
    {
        $payload = $this->request($uri);
        /** @var list<array<string, mixed>> $members */
        $members = $payload['member'] ?? $payload['hydra:member'] ?? [];

        return array_map(static fn (array $item): string => (string) ($item['slug'] ?? ''), $members);
    }

    private function createAccommodation(string $slug): Accommodation
    {
        $accommodation = new Accommodation($slug, Resort::Chm, AccommodationType::MobileHome, 4, 2, 'Test accommodation');
        $this->em->persist($accommodation);
        $this->em->flush();

        return $accommodation;
    }
}
