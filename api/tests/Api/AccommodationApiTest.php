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
use App\Factory\AccommodationFactory;
use App\Factory\PricePeriodFactory;
use App\Tests\ApiTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class AccommodationApiTest extends ApiTestCase
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

    public function testADraftIsAbsentFromTheCollection(): void
    {
        $this->createAccommodation('published-one');
        $this->createAccommodation('draft-one', published: false);

        $this->client->request('GET', '/api/accommodations');
        $data = json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertResponseIsSuccessful();
        self::assertSame(1, $data['totalItems']);
        self::assertSame('published-one', $data['member'][0]['slug']);
    }

    public function testADraftDetailPageIsNotFound(): void
    {
        $this->createAccommodation('draft-one', published: false);

        $this->client->request('GET', '/api/accommodations/draft-one');

        self::assertResponseStatusCodeSame(404);
    }

    public function testEachSearchCardCarriesItsCoverOnly(): void
    {
        $accommodation = $this->createAccommodation('mobile-home-photos');
        $cover = $accommodation->getPhotos()[0];
        $accommodation->addPhoto(new Photo($accommodation, 1600, 1200));
        $this->em->flush();

        $payload = $this->request('/api/accommodations');
        /** @var array<string, mixed> $card */
        $card = $payload['member'][0] ?? [];
        /** @var array<string, mixed> $coverPayload */
        $coverPayload = $card['cover'] ?? [];

        self::assertResponseIsSuccessful();
        self::assertSame([
            'url' => 'http://localhost/media/photos/'.$cover->getId()->toRfc4122().'.webp',
            'thumbUrl' => 'http://localhost/media/photos/'.$cover->getId()->toRfc4122().'-thumb.webp',
            'width' => 1600,
            'height' => 1066,
        ], array_diff_key($coverPayload, ['@id' => true, '@type' => true]));
        // The search page only needs the cover: the gallery is for the detail page.
        self::assertSame([], $card['photos'] ?? null);
    }

    public function testTheDetailPageListsThePhotosCoverFirst(): void
    {
        $accommodation = $this->createAccommodation('mobile-home-galerie');
        $first = $accommodation->getPhotos()[0];
        $second = new Photo($accommodation, 1200, 1600);
        $accommodation->addPhoto($second);
        $accommodation->reorderPhotos([$second->getId()->toRfc4122(), $first->getId()->toRfc4122()]);
        $this->em->flush();

        $payload = $this->request('/api/accommodations/mobile-home-galerie');
        /** @var list<array<string, mixed>> $photos */
        $photos = $payload['photos'] ?? [];

        self::assertResponseIsSuccessful();
        self::assertCount(2, $photos);
        self::assertStringContainsString($second->getId()->toRfc4122(), (string) ($photos[0]['url'] ?? ''));
        self::assertStringContainsString($first->getId()->toRfc4122(), (string) ($photos[1]['url'] ?? ''));
        self::assertSame($photos[0], $payload['cover'] ?? null);
        self::assertArrayNotHasKey('id', $photos[0], 'Nothing to act on from the public site.');
    }

    public function testAnAccommodationWithoutPhotoHasNoCover(): void
    {
        // The factory publishes without any photo, like the development fixtures.
        AccommodationFactory::createOne(['slug' => 'sans-photo']);

        $payload = $this->request('/api/accommodations/sans-photo');

        self::assertResponseIsSuccessful();
        self::assertArrayHasKey('cover', $payload);
        self::assertNull($payload['cover']);
        self::assertSame([], $payload['photos'] ?? null);
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

    private function createAccommodation(string $slug, bool $published = true): Accommodation
    {
        $owner = new User($slug.'@example.com', 'Proprietaire test');
        $this->em->persist($owner);

        $owner->verifyEmail(new \DateTimeImmutable());
        $accommodation = new Accommodation($slug, Resort::Chm, AccommodationType::MobileHome, 4, 2, 'Test accommodation with a description long enough to be published.', $owner);
        $accommodation->setDistrict($this->testDistrict());
        // A published listing needs a photo: an entity is enough, no file is read here.
        $accommodation->addPhoto(new Photo($accommodation, 1600, 1066));
        if ($published) {
            $accommodation->publish();
        }
        $this->em->persist($accommodation);
        $this->em->flush();

        return $accommodation;
    }

    public function testTheAccommodationPageListsItsPricePeriods(): void
    {
        $accommodation = AccommodationFactory::createOne(['slug' => 'mobil-home-tarifs']);
        // Créée en second, mais attendue en premier : la fiche doit trier par date.
        PricePeriodFactory::createOne([
            'accommodation' => $accommodation,
            'startDate' => new \DateTimeImmutable('2027-07-01'),
            'endDate' => new \DateTimeImmutable('2027-08-01'),
            'weeklyPrice' => 70000,
            'nightlyPrice' => null,
            'minimumNights' => 7,
        ]);
        PricePeriodFactory::createOne([
            'accommodation' => $accommodation,
            'startDate' => new \DateTimeImmutable('2027-04-01'),
            'endDate' => new \DateTimeImmutable('2027-07-01'),
            'weeklyPrice' => 40000,
            'nightlyPrice' => 7000,
            'minimumNights' => 3,
        ]);
        // Le tarif d'un autre logement ne doit pas apparaître ici.
        PricePeriodFactory::createOne(['weeklyPrice' => 99000]);

        $this->client->request('GET', '/api/accommodations/mobil-home-tarifs', server: ['HTTP_ACCEPT' => 'application/ld+json']);

        self::assertResponseIsSuccessful();

        /** @var array{pricePeriods: list<array{weeklyPrice: ?int, nightlyPrice: ?int, minimumNights: int}>} $body */
        $body = json_decode((string) $this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(
            [[40000, 7000, 3], [70000, null, 7]],
            array_map(
                static fn (array $period): array => [$period['weeklyPrice'], $period['nightlyPrice'], $period['minimumNights']],
                $body['pricePeriods'],
            ),
        );
    }
}
