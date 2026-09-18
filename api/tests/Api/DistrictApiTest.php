<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Accommodation;
use App\Entity\PricePeriod;
use App\Enum\DistrictArea;
use App\Factory\AccommodationFactory;
use App\Factory\DistrictFactory;
use App\Tests\ApiTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class DistrictApiTest extends ApiTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
    }

    public function testListsEveryDistrictInPlanOrderWithItsCount(): void
    {
        $sables = DistrictFactory::createOne(['name' => 'Sables', 'area' => DistrictArea::Dunes, 'position' => 0]);
        DistrictFactory::createOne(['name' => 'Médoc', 'area' => DistrictArea::Roadside, 'position' => 1]);
        AccommodationFactory::createMany(2, ['district' => $sables]);

        /** @var array{member: list<array{slug: string, area: ?string, accommodationCount: int}>} $body */
        $body = $this->fetch('/api/districts');

        // Un quartier sans logement reste dans la liste : sa page existe quand même.
        self::assertSame(
            [['sables', 'dunes', 2], ['medoc', 'roadside', 0]],
            array_map(
                static fn (array $district): array => [$district['slug'], $district['area'], $district['accommodationCount']],
                $body['member'],
            ),
        );
    }

    public function testShowsADistrictWithItsWeeklyPriceRange(): void
    {
        $europa = DistrictFactory::createOne([
            'name' => 'Europa',
            'area' => DistrictArea::Dunes,
            'intro' => 'Texte vérifié.',
            'highlights' => ['Point de regroupement incendie n° 9'],
        ]);

        $cheap = AccommodationFactory::createOne(['district' => $europa]);
        $expensive = AccommodationFactory::createOne(['district' => $europa]);
        AccommodationFactory::createOne(['district' => $europa]); // sans tarif : hors fourchette
        $elsewhere = AccommodationFactory::createOne(); // autre quartier : ignoré

        $this->weeklyRate($cheap, 40000);
        $this->weeklyRate($expensive, 70000);
        $this->weeklyRate($elsewhere, 10000);

        /** @var array<string, mixed> $body */
        $body = $this->fetch('/api/districts/europa');

        self::assertSame('Europa', $body['name']);
        self::assertSame('dunes', $body['area']);
        self::assertSame(3, $body['accommodationCount']);
        self::assertSame(40000, $body['priceMin']);
        self::assertSame(70000, $body['priceMax']);
        self::assertSame('Texte vérifié.', $body['intro']);
        self::assertSame(['Point de regroupement incendie n° 9'], $body['highlights']);
    }

    public function testADistrictWithoutRatesHasNoPriceRange(): void
    {
        $pins = DistrictFactory::createOne(['name' => 'Pins']);
        AccommodationFactory::createOne(['district' => $pins]);

        /** @var array<string, mixed> $body */
        $body = $this->fetch('/api/districts/pins');

        self::assertNull($body['area']);
        self::assertSame(1, $body['accommodationCount']);
        self::assertNull($body['priceMin']);
        self::assertNull($body['priceMax']);
    }

    public function testAnUnknownDistrictIsNotFound(): void
    {
        $this->client->request('GET', '/api/districts/atlantide', server: ['HTTP_ACCEPT' => 'application/ld+json']);

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @return array<string, mixed>
     */
    private function fetch(string $url): array
    {
        $this->client->request('GET', $url, server: ['HTTP_ACCEPT' => 'application/ld+json']);

        self::assertResponseIsSuccessful();

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

        return $body;
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
