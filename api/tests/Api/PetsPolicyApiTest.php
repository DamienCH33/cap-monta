<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Accommodation;
use App\Entity\Photo;
use App\Entity\User;
use App\Enum\AccommodationType;
use App\Enum\PetsPolicy;
use App\Enum\Resort;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * « Animaux non acceptés » is said before the guest asks: excluded from the search, refused
 * by the quote. « Sur demande » lets the request through, for the owner to decide.
 */
final class PetsPolicyApiTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private User $owner;
    private string $arrival;
    private string $departure;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $this->em = $em;
        (new ORMPurger($this->em))->purge();

        $this->owner = new User('alice@example.com', 'Alice');
        $this->owner->verifyEmail(new \DateTimeImmutable());
        $this->em->persist($this->owner);

        $start = new \DateTimeImmutable('saturday +10 weeks');
        $this->arrival = $start->format('Y-m-d');
        $this->departure = $start->modify('+7 days')->format('Y-m-d');
    }

    public function testASearchWithAnAnimalLeavesOutTheListingsThatRefuseThem(): void
    {
        $this->listing('chiens-bienvenus', PetsPolicy::Allowed);
        $this->listing('sur-demande', PetsPolicy::OnRequest);
        $this->listing('sans-animaux', PetsPolicy::NotAllowed);

        self::assertSame(['chiens-bienvenus', 'sans-animaux', 'sur-demande'], $this->search(''));
        self::assertSame(['chiens-bienvenus', 'sur-demande'], $this->search('&pets=1'));
    }

    public function testTheQuoteAndTheRequestRefuseAnAnimalWhereTheyAreNotAllowed(): void
    {
        $this->listing('sans-animaux', PetsPolicy::NotAllowed);

        $this->client->request('GET', sprintf('/api/accommodations/sans-animaux/quote?arrival=%s&departure=%s&guests=2&pets=1', $this->arrival, $this->departure));
        self::assertSame('pets_not_allowed', $this->json()['refusal']);

        $this->client->request('GET', sprintf('/api/accommodations/sans-animaux/quote?arrival=%s&departure=%s&guests=2', $this->arrival, $this->departure));
        self::assertTrue($this->json()['available'], 'Without an animal, nothing changes.');

        $this->client->request('POST', '/api/booking-requests', server: ['CONTENT_TYPE' => 'application/ld+json'], content: json_encode([
            'accommodationSlug' => 'sans-animaux',
            'arrival' => $this->arrival,
            'departure' => $this->departure,
            'adults' => 2,
            'pets' => 1,
            'guestName' => 'Jeanne Martin',
            'guestEmail' => 'jeanne@example.com',
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(409);
        self::assertStringContainsString('animaux', (string) $this->json()['detail']);
    }

    public function testTheOwnerSetsHisPolicyAndThePublicSeesIt(): void
    {
        $this->listing('bungalow-alice', PetsPolicy::OnRequest);
        $this->client->loginUser($this->owner, 'main');

        $this->client->request('PATCH', '/api/owner/accommodations/bungalow-alice', server: [
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ], content: '{"petsPolicy":"not_allowed"}');
        self::assertResponseIsSuccessful();
        self::assertSame('not_allowed', $this->json()['petsPolicy']);

        $this->client->request('GET', '/api/accommodations/bungalow-alice');
        self::assertSame('not_allowed', $this->json()['petsPolicy']);
    }

    private function listing(string $slug, PetsPolicy $policy): void
    {
        $accommodation = new Accommodation($slug, Resort::Euronat, AccommodationType::Bungalow, 6, 3, 'Test accommodation with a description long enough to be published.', $this->owner);
        $accommodation->setPetsPolicy($policy);
        $accommodation->addPhoto(new Photo($accommodation, 1600, 1066));
        $accommodation->publish();
        $this->em->persist($accommodation);
        $this->em->flush();
    }

    /**
     * @return list<string>
     */
    private function search(string $extra): array
    {
        $this->client->request('GET', sprintf('/api/accommodations?arrival=%s&departure=%s&guests=2%s', $this->arrival, $this->departure, $extra));
        self::assertResponseIsSuccessful();

        return array_column($this->json()['member'], 'slug');
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
