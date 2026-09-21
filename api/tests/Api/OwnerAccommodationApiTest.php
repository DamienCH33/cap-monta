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
