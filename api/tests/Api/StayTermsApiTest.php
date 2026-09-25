<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Accommodation;
use App\Entity\Photo;
use App\Entity\PricePeriod;
use App\Entity\StayTerms;
use App\Entity\User;
use App\Enum\AccommodationType;
use App\Enum\Resort;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The owner states his conditions (times, deposit, cancellation) and the fees on top of the
 * rent; the quote adds the mandatory ones (tourist tax per adult, resort fee per guest) so
 * that the traveller knows the real price before asking.
 */
final class StayTermsApiTest extends WebTestCase
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

    public function testTheOwnerStatesHisConditionsAndThePublicSeesThem(): void
    {
        $this->listing('bungalow-alice', new StayTerms());
        $this->client->loginUser($this->owner, 'main');

        $this->patch('bungalow-alice', ['terms' => [
            'checkInFrom' => '16:00',
            'checkOutBefore' => '10:00',
            'depositPercent' => 30,
            'securityDeposit' => 30000,
            'cancellationPolicy' => '  Acompte rendu jusqu’à 30 jours avant.  ',
            'touristTax' => 88,
        ]]);
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/api/accommodations/bungalow-alice');
        $terms = $this->json()['terms'];
        self::assertSame('16:00', $terms['checkInFrom']);
        self::assertSame(30, $terms['depositPercent']);
        self::assertSame('Acompte rendu jusqu’à 30 jours avant.', $terms['cancellationPolicy']);
        self::assertNull($terms['resortFee'], 'What the owner does not say stays unknown.');

        $this->patch('bungalow-alice', ['terms' => null]);
        $this->client->request('GET', '/api/accommodations/bungalow-alice');
        self::assertNull($this->json()['terms']['checkInFrom'], 'null removes every condition.');
    }

    public function testImpossibleConditionsAreRefusedInFrench(): void
    {
        $this->listing('bungalow-alice', new StayTerms());
        $this->client->loginUser($this->owner, 'main');

        $this->patch('bungalow-alice', ['terms' => ['checkInFrom' => '25:00', 'depositPercent' => 130, 'touristTax' => -1]]);

        self::assertResponseStatusCodeSame(422);
        $paths = array_column($this->json()['violations'], 'propertyPath');
        sort($paths);
        self::assertSame(['terms.checkInFrom', 'terms.depositPercent', 'terms.touristTax'], $paths);
    }

    public function testTheQuoteAddsTheMandatoryFeesAndListsTheOptionalOnes(): void
    {
        $this->listing('bungalow-alice', new StayTerms(touristTax: 88, resortFee: 450, cleaningFee: 6000, linenFee: 1500));

        // 7 nights, 4 guests of whom 2 adults: tax 0,88 € × 2 × 7, fee 4,50 € × 4 × 7.
        $quote = $this->quote('guests=4&adults=2');

        self::assertSame(70000, $quote['total']);
        self::assertSame([
            ['code' => 'tourist_tax', 'amount' => 1232, 'optional' => false],
            ['code' => 'resort_fee', 'amount' => 12600, 'optional' => false],
            ['code' => 'cleaning', 'amount' => 6000, 'optional' => true],
            ['code' => 'linen', 'amount' => 6000, 'optional' => true],
        ], $quote['extras']);
        self::assertSame(70000 + 1232 + 12600, $quote['estimatedTotal'], 'Options are not in the total.');
        self::assertSame([], $quote['unknownFees']);
    }

    public function testFeesTheOwnerDidNotStateAreFlaggedNotGuessed(): void
    {
        $this->listing('bungalow-alice', new StayTerms());

        $quote = $this->quote('guests=2');

        self::assertSame([], $quote['extras']);
        self::assertSame(['tourist_tax', 'resort_fee'], $quote['unknownFees']);
        self::assertSame(70000, $quote['estimatedTotal']);

        $this->client->request('GET', sprintf('/api/accommodations/bungalow-alice/quote?arrival=%s&departure=%s&guests=2&adults=3', $this->arrival, $this->departure));
        self::assertResponseStatusCodeSame(400);
    }

    private function listing(string $slug, StayTerms $terms): void
    {
        $accommodation = new Accommodation($slug, Resort::Euronat, AccommodationType::Bungalow, 6, 3, 'Test accommodation with a description long enough to be published.', $this->owner);
        $accommodation->setTerms($terms);
        $accommodation->addPhoto(new Photo($accommodation, 1600, 1066));
        $accommodation->publish();
        $this->em->persist($accommodation);

        $period = new PricePeriod($accommodation, new \DateTimeImmutable($this->arrival), new \DateTimeImmutable($this->departure));
        $period->setWeeklyPrice(70000);
        $this->em->persist($period);
        $this->em->flush();
    }

    /**
     * @param array<string, mixed> $body
     */
    private function patch(string $slug, array $body): void
    {
        $this->client->request('PATCH', '/api/owner/accommodations/'.$slug, server: [
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ], content: json_encode($body, \JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<mixed>
     */
    private function quote(string $people): array
    {
        $this->client->request('GET', sprintf('/api/accommodations/bungalow-alice/quote?arrival=%s&departure=%s&%s', $this->arrival, $this->departure, $people));
        self::assertResponseIsSuccessful();

        return $this->json();
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
