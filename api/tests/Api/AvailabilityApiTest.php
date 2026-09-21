<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Accommodation;
use App\Entity\District;
use App\Entity\Photo;
use App\Entity\Unavailability;
use App\Entity\User;
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

    private function block(Accommodation $accommodation, \DateTimeImmutable $start, \DateTimeImmutable $end): void
    {
        $this->em->persist(new Unavailability($accommodation, $start, $end, UnavailabilitySource::Block));
        $this->em->flush();
    }
}
