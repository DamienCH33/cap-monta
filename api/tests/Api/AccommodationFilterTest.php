<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Accommodation;
use App\Entity\PricePeriod;
use App\Enum\AccommodationType;
use App\Factory\AccommodationFactory;
use App\Factory\DistrictFactory;
use App\Tests\ApiTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class AccommodationFilterTest extends ApiTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
    }

    public function testFiltersOnSeveralTypes(): void
    {
        AccommodationFactory::createOne(['slug' => 'a-caravane', 'type' => AccommodationType::Caravan]);
        AccommodationFactory::createOne(['slug' => 'b-bungalow', 'type' => AccommodationType::Bungalow]);
        AccommodationFactory::createOne(['slug' => 'c-mobil-home', 'type' => AccommodationType::MobileHome]);

        self::assertSame(['a-caravane', 'b-bungalow'], $this->slugs('type[]=caravan&type[]=bungalow'));
    }

    public function testFiltersOnSeveralDistricts(): void
    {
        AccommodationFactory::createOne(['slug' => 'a-europa', 'district' => DistrictFactory::named('Europa')]);
        AccommodationFactory::createOne(['slug' => 'b-lalande', 'district' => DistrictFactory::named('Lalande')]);
        AccommodationFactory::createOne(['slug' => 'c-medoc', 'district' => DistrictFactory::named('Médoc')]);

        self::assertSame(['a-europa', 'b-lalande'], $this->slugs('district[]=Europa&district[]=Lalande'));
    }

    public function testStillAcceptsASingleDistrict(): void
    {
        AccommodationFactory::createOne(['slug' => 'a-europa', 'district' => DistrictFactory::named('Europa')]);
        AccommodationFactory::createOne(['slug' => 'b-lalande', 'district' => DistrictFactory::named('Lalande')]);

        self::assertSame(['a-europa'], $this->slugs('district=Europa'));
    }

    public function testRequiresAMinimumOfBedrooms(): void
    {
        AccommodationFactory::createOne(['slug' => 'a-une-chambre', 'bedrooms' => 1]);
        AccommodationFactory::createOne(['slug' => 'b-deux-chambres', 'bedrooms' => 2]);
        AccommodationFactory::createOne(['slug' => 'c-trois-chambres', 'bedrooms' => 3]);

        self::assertSame(['b-deux-chambres', 'c-trois-chambres'], $this->slugs('bedrooms=2'));
    }

    public function testRequiresEveryAmenity(): void
    {
        AccommodationFactory::createOne(['slug' => 'a-complet', 'amenities' => ['wifi', 'terrasse', 'plancha']]);
        AccommodationFactory::createOne(['slug' => 'b-wifi-seul', 'amenities' => ['wifi']]);
        AccommodationFactory::createOne(['slug' => 'c-rien', 'amenities' => []]);

        self::assertSame(['a-complet', 'b-wifi-seul'], $this->slugs('amenities[]=wifi'));
        self::assertSame(['a-complet'], $this->slugs('amenities[]=wifi&amenities[]=terrasse'));
        self::assertSame([], $this->slugs('amenities[]=piscine'));
    }

    public function testSortsByWeeklyPriceWithUnpricedLast(): void
    {
        $cheap = AccommodationFactory::createOne(['slug' => 'a-pas-cher']);
        $expensive = AccommodationFactory::createOne(['slug' => 'b-cher']);
        AccommodationFactory::createOne(['slug' => 'c-sans-tarif']);

        $this->weeklyRate($cheap, 40000);
        $this->weeklyRate($expensive, 70000);

        self::assertSame(['a-pas-cher', 'b-cher', 'c-sans-tarif'], $this->slugs('order=price_asc'));
        self::assertSame(['b-cher', 'a-pas-cher', 'c-sans-tarif'], $this->slugs('order=price_desc'));
    }

    public function testRejectsAnUnknownType(): void
    {
        $this->client->request('GET', '/api/accommodations?type[]=yacht', server: ['HTTP_ACCEPT' => 'application/ld+json']);

        // 422 si API Platform refuse la valeur, 400 si c'est StayQuery : les deux sont une erreur du client.
        self::assertContains($this->client->getResponse()->getStatusCode(), [400, 422]);
    }

    /**
     * @return list<string>
     */
    private function slugs(string $query): array
    {
        $this->client->request('GET', '/api/accommodations?'.$query, server: ['HTTP_ACCEPT' => 'application/ld+json']);

        self::assertResponseIsSuccessful();

        /** @var array{member: list<array{slug: string}>} $body */
        $body = json_decode((string) $this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

        return array_column($body['member'], 'slug');
    }

    private function weeklyRate(Accommodation $accommodation, int $weeklyPrice): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $period = new PricePeriod($accommodation, new \DateTimeImmutable('2027-06-01'), new \DateTimeImmutable('2027-09-01'));
        $period->setWeeklyPrice($weeklyPrice);

        $em->persist($period);
        $em->flush();
    }
}
