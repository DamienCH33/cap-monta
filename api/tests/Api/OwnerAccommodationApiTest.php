<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Accommodation;
use App\Entity\District;
use App\Entity\User;
use App\Enum\AccommodationType;
use App\Enum\Resort;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class OwnerAccommodationApiTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $this->em = $em;

        // Every test starts from an empty database: no leftover owner or slug.
        (new ORMPurger($this->em))->purge();
    }

    public function testAnAnonymousVisitorIsRejected(): void
    {
        $this->client->request('GET', '/api/owner/accommodations');

        self::assertResponseStatusCodeSame(401);
    }

    public function testTheOwnerSeesAllHisAccommodationsDraftsIncluded(): void
    {
        $alice = $this->createOwner('alice@example.com');
        $bob = $this->createOwner('bob@example.com');
        $this->createAccommodation('alice-published', $alice, published: true);
        $this->createAccommodation('alice-draft', $alice, published: false);
        $this->createAccommodation('bob-published', $bob, published: true);
        $this->em->flush();

        $this->client->loginUser($alice, 'main');
        $this->client->request('GET', '/api/owner/accommodations');

        self::assertResponseIsSuccessful();
        $data = $this->json();
        self::assertSame(
            [['alice-draft', 'draft'], ['alice-published', 'published']],
            array_map(static fn (array $item): array => [$item['slug'], $item['status']], $data['member']),
        );
    }

    public function testTheOwnerCanReadHisDraft(): void
    {
        $alice = $this->createOwner('alice@example.com');
        $this->createAccommodation('alice-draft', $alice, published: false);
        $this->em->flush();

        $this->client->loginUser($alice, 'main');
        $this->client->request('GET', '/api/owner/accommodations/alice-draft');

        self::assertResponseIsSuccessful();
        self::assertSame('draft', $this->json()['status']);
    }

    public function testSomeoneElsesAccommodationIsNotFound(): void
    {
        $alice = $this->createOwner('alice@example.com');
        $bob = $this->createOwner('bob@example.com');
        $this->createAccommodation('bob-draft', $bob, published: false);
        $this->em->flush();

        $this->client->loginUser($alice, 'main');
        $this->client->request('GET', '/api/owner/accommodations/bob-draft');

        // 404 and not 403: a 403 would confirm that the slug exists.
        self::assertResponseStatusCodeSame(404);
    }

    private const array VALID_PAYLOAD = ['resort' => 'euronat', 'type' => 'mobile_home', 'capacity' => 6, 'bedrooms' => 2];

    public function testAnAnonymousVisitorCannotCreate(): void
    {
        $this->post(self::VALID_PAYLOAD);

        self::assertResponseStatusCodeSame(401);
    }

    public function testAnEmptyPayloadNamesEveryMissingField(): void
    {
        $this->client->loginUser($this->createOwnerAndFlush(), 'main');
        $this->post([]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(
            ['resort', 'type', 'capacity', 'bedrooms'],
            array_column($this->json()['violations'], 'propertyPath'),
        );
    }

    public function testANewAccommodationIsADraftOwnedByTheCaller(): void
    {
        $this->client->loginUser($this->createOwnerAndFlush(), 'main');
        $this->post(self::VALID_PAYLOAD);

        self::assertResponseStatusCodeSame(201);
        $created = $this->json();
        self::assertSame('mobil-home-euronat-6-personnes', $created['slug']);
        self::assertSame('draft', $created['status']);

        // It shows up in the caller's own list: he is the owner.
        $this->client->request('GET', '/api/owner/accommodations');
        self::assertSame(['mobil-home-euronat-6-personnes'], array_column($this->json()['member'], 'slug'));
    }

    public function testATakenSlugGetsASuffix(): void
    {
        $this->client->loginUser($this->createOwnerAndFlush(), 'main');
        $this->post(self::VALID_PAYLOAD);
        $this->post(self::VALID_PAYLOAD);

        self::assertResponseStatusCodeSame(201);
        self::assertSame('mobil-home-euronat-6-personnes-2', $this->json()['slug']);
    }

    public function testAnUnknownDistrictIsRejected(): void
    {
        $this->client->loginUser($this->createOwnerAndFlush(), 'main');
        $this->post(['resort' => 'chm', 'type' => 'bungalow', 'capacity' => 8, 'bedrooms' => 3, 'district' => 'atlantide']);

        self::assertResponseStatusCodeSame(422);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function post(array $payload): void
    {
        $this->client->request(
            'POST',
            '/api/owner/accommodations',
            server: ['CONTENT_TYPE' => 'application/ld+json'],
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );
    }

    private function createOwnerAndFlush(): User
    {
        $owner = $this->createOwner('alice@example.com');
        $this->em->flush();

        return $owner;
    }

    public function testAnAnonymousVisitorCannotEdit(): void
    {
        $this->patch('anything', ['capacity' => 2]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testOnlyTheFieldsSentAreChanged(): void
    {
        $alice = $this->createOwner('alice@example.com');
        $this->createAccommodation('alice-draft', $alice, published: false);
        $this->em->flush();

        $this->client->loginUser($alice, 'main');
        $this->patch('alice-draft', ['capacity' => 6, 'description' => 'Nouvelle description']);

        self::assertResponseIsSuccessful();
        $edited = $this->json();
        self::assertSame(6, $edited['capacity']);
        self::assertSame('Nouvelle description', $edited['description']);
        self::assertSame(2, $edited['bedrooms'], 'A field that was not sent keeps its value.');
        self::assertSame('alice-draft', $edited['slug']);
    }

    public function testAPublishedAccommodationCannotBecomeIncomplete(): void
    {
        $alice = $this->createOwner('alice@example.com');
        $description = str_repeat('Vue sur la pinède. ', 4);
        $accommodation = new Accommodation('alice-euronat', Resort::Euronat, AccommodationType::Bungalow, 6, 3, $description, $alice);
        $accommodation->publish();
        $this->em->persist($accommodation);
        $this->em->flush();

        $this->client->loginUser($alice, 'main');
        $this->patch('alice-euronat', ['description' => '']);

        self::assertResponseStatusCodeSame(422);

        // Nothing was written: the description is still the original one.
        $this->client->request('GET', '/api/owner/accommodations/alice-euronat');
        self::assertSame(trim($description), trim((string) $this->json()['description']));
    }

    public function testSomeoneElsesAccommodationCannotBeEdited(): void
    {
        $alice = $this->createOwner('alice@example.com');
        $bob = $this->createOwner('bob@example.com');
        $this->createAccommodation('bob-draft', $bob, published: false);
        $this->em->flush();

        $this->client->loginUser($alice, 'main');
        $this->patch('bob-draft', ['capacity' => 2]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testTheSlugCannotBeChanged(): void
    {
        $alice = $this->createOwner('alice@example.com');
        $this->createAccommodation('alice-draft', $alice, published: false);
        $this->em->flush();

        $this->client->loginUser($alice, 'main');
        $this->patch('alice-draft', ['slug' => 'pirate']);

        self::assertResponseStatusCodeSame(400);
    }

    public function testAnOutOfRangeValueIsRejected(): void
    {
        $alice = $this->createOwner('alice@example.com');
        $this->createAccommodation('alice-draft', $alice, published: false);
        $this->em->flush();

        $this->client->loginUser($alice, 'main');
        $this->patch('alice-draft', ['capacity' => 40]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['capacity'], array_column($this->json()['violations'], 'propertyPath'));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function patch(string $slug, array $payload): void
    {
        $this->client->request(
            'PATCH',
            '/api/owner/accommodations/'.$slug,
            server: ['CONTENT_TYPE' => 'application/merge-patch+json'],
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );
    }

    public function testAnAnonymousVisitorCannotPublish(): void
    {
        $this->transition('anything', 'publish');

        self::assertResponseStatusCodeSame(401);
    }

    public function testACompleteDraftGoesOnline(): void
    {
        $alice = $this->createOwner('alice@example.com');
        $this->createAccommodation('alice-draft', $alice, published: false);
        $this->em->flush();

        $this->client->loginUser($alice, 'main');
        $this->transition('alice-draft', 'publish');

        self::assertResponseIsSuccessful();
        self::assertSame('published', $this->json()['status']);

        $this->client->request('GET', '/api/accommodations/alice-draft');
        self::assertResponseIsSuccessful();
    }

    public function testAnIncompleteDraftIsRefused(): void
    {
        $alice = $this->createOwner('alice@example.com');
        $this->em->persist(new Accommodation('alice-short', Resort::Chm, AccommodationType::MobileHome, 4, 2, 'Trop court', $alice));
        $this->em->flush();

        $this->client->loginUser($alice, 'main');
        $this->transition('alice-short', 'publish');

        self::assertResponseStatusCodeSame(422);

        $this->client->request('GET', '/api/owner/accommodations/alice-short');
        self::assertSame('draft', $this->json()['status']);
    }

    public function testAnUnverifiedOwnerCannotPublish(): void
    {
        $carol = new User('carol@example.com', 'Carol');
        $this->em->persist($carol);
        $this->createAccommodation('carol-draft', $carol, published: false);
        $this->em->flush();

        $this->client->loginUser($carol, 'main');
        $this->transition('carol-draft', 'publish');

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('adresse email', (string) $this->json()['detail']);
    }

    public function testADraftCannotBeArchived(): void
    {
        $alice = $this->createOwner('alice@example.com');
        $this->createAccommodation('alice-draft', $alice, published: false);
        $this->em->flush();

        $this->client->loginUser($alice, 'main');
        $this->transition('alice-draft', 'archive');

        self::assertResponseStatusCodeSame(409);
    }

    public function testAnArchivedAccommodationGoesBackOnlineAtTheSameAddress(): void
    {
        $alice = $this->createOwner('alice@example.com');
        $this->createAccommodation('alice-home', $alice, published: true);
        $this->em->flush();

        $this->client->loginUser($alice, 'main');

        $this->transition('alice-home', 'archive');
        self::assertSame('archived', $this->json()['status']);
        $this->client->request('GET', '/api/accommodations/alice-home');
        self::assertResponseStatusCodeSame(404);

        $this->transition('alice-home', 'publish');
        self::assertSame('published', $this->json()['status']);
        $this->client->request('GET', '/api/accommodations/alice-home');
        self::assertResponseIsSuccessful();
    }

    public function testSomeoneElsesAccommodationCannotBePublished(): void
    {
        $alice = $this->createOwner('alice@example.com');
        $bob = $this->createOwner('bob@example.com');
        $this->createAccommodation('bob-draft', $bob, published: false);
        $this->em->flush();

        $this->client->loginUser($alice, 'main');
        $this->transition('bob-draft', 'publish');

        self::assertResponseStatusCodeSame(404);
    }

    private function transition(string $slug, string $action): void
    {
        $this->client->request('POST', '/api/owner/accommodations/'.$slug.'/'.$action);
    }

    private function createOwner(string $email): User
    {
        $owner = new User($email, 'Owner test');
        $owner->verifyEmail(new \DateTimeImmutable());
        $this->em->persist($owner);

        return $owner;
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

    private function createAccommodation(string $slug, User $owner, bool $published): void
    {
        $accommodation = new Accommodation($slug, Resort::Chm, AccommodationType::MobileHome, 4, 2, 'Test accommodation with a description long enough to be published.', $owner);
        $accommodation->setDistrict($this->testDistrict());

        if ($published) {
            $accommodation->publish();
        }

        $this->em->persist($accommodation);
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
