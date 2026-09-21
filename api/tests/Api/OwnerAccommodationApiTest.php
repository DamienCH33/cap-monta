<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Accommodation;
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

    private function createOwner(string $email): User
    {
        $owner = new User($email, 'Owner test');
        $owner->verifyEmail(new \DateTimeImmutable());
        $this->em->persist($owner);

        return $owner;
    }

    private function createAccommodation(string $slug, User $owner, bool $published): void
    {
        $accommodation = new Accommodation($slug, Resort::Chm, AccommodationType::MobileHome, 4, 2, 'Test accommodation', $owner);

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
