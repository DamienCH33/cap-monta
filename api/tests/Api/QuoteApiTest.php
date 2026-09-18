<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Factory\AccommodationFactory;
use App\Factory\PricePeriodFactory;
use App\Factory\UnavailabilityFactory;
use App\Tests\ApiTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * GET /api/accommodations/{slug}/quote : le devis décrit un séjour, il ne le refuse pas.
 * Un refus est une réponse 200 avec `available: false` et une raison lisible par le front.
 */
final class QuoteApiTest extends ApiTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
    }

    public function testAFreeWeekCostsTheWeeklyPrice(): void
    {
        $accommodation = AccommodationFactory::createOne(['slug' => 'mobil-home-libre']);
        PricePeriodFactory::createOne([
            'accommodation' => $accommodation,
            'startDate' => new \DateTimeImmutable('2027-07-01'),
            'endDate' => new \DateTimeImmutable('2027-08-01'),
            'weeklyPrice' => 70000,
            'nightlyPrice' => null,
            'minimumNights' => 7,
        ]);

        $body = $this->quote('mobil-home-libre', '2027-07-03', '2027-07-10', 2);

        self::assertTrue($body['available']);
        self::assertNull($body['refusal']);
        self::assertSame(7, $body['nights']);
        self::assertSame(70000, $body['total']);
        self::assertSame(7, $body['minimumNights']);
    }

    public function testTooManyGuestsIsDescribed(): void
    {
        AccommodationFactory::createOne(['slug' => 'caravane-4', 'capacity' => 4, 'maxCapacity' => 4]);

        $body = $this->quote('caravane-4', '2027-07-03', '2027-07-10', 6);

        self::assertFalse($body['available']);
        self::assertSame('too_many_guests', $body['refusal']);
        // Le front affiche « accueille 4 personnes au maximum » : la valeur doit être dans la réponse.
        self::assertSame(4, $body['maxCapacity']);
    }

    public function testTakenDatesAreReported(): void
    {
        $accommodation = AccommodationFactory::createOne(['slug' => 'bungalow-occupe']);
        UnavailabilityFactory::createOne([
            'accommodation' => $accommodation,
            'startDate' => new \DateTimeImmutable('2027-07-05'),
            'endDate' => new \DateTimeImmutable('2027-07-12'),
        ]);

        $body = $this->quote('bungalow-occupe', '2027-07-03', '2027-07-10', 2);

        self::assertFalse($body['available']);
        self::assertSame('unavailable', $body['refusal']);
    }

    public function testAStayShorterThanTheMinimumIsRefused(): void
    {
        $accommodation = AccommodationFactory::createOne(['slug' => 'mobil-home-minimum']);
        PricePeriodFactory::createOne([
            'accommodation' => $accommodation,
            'startDate' => new \DateTimeImmutable('2027-07-01'),
            'endDate' => new \DateTimeImmutable('2027-08-01'),
            'weeklyPrice' => 70000,
            'nightlyPrice' => 12000,
            'minimumNights' => 7,
        ]);

        $body = $this->quote('mobil-home-minimum', '2027-07-03', '2027-07-06', 2);

        self::assertSame(3, $body['nights']);
        self::assertFalse($body['available']);
        self::assertSame('stay_too_short', $body['refusal']);
        self::assertSame(7, $body['minimumNights']);
    }

    public function testWithoutPublishedRatesTheStayIsPossibleButHasNoTotal(): void
    {
        AccommodationFactory::createOne(['slug' => 'sans-tarif']);

        $body = $this->quote('sans-tarif', '2027-07-03', '2027-07-10', 2);

        self::assertTrue($body['available']);
        self::assertNull($body['total']);
        // Aucune période traversée : le minimum retombe à une nuit.
        self::assertSame(1, $body['minimumNights']);
    }

    public function testDatesAreRequired(): void
    {
        AccommodationFactory::createOne(['slug' => 'mobil-home-sans-dates']);

        $this->client->request('GET', '/api/accommodations/mobil-home-sans-dates/quote', server: [
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        // 422 si API Platform bloque d'après le schéma, 400 si c'est le provider.
        self::assertContains($this->client->getResponse()->getStatusCode(), [400, 422]);
    }

    public function testAnUnknownAccommodationIsNotFound(): void
    {
        $this->client->request('GET', '/api/accommodations/nope/quote?arrival=2027-07-03&departure=2027-07-10', server: [
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @return array<string, mixed>
     */
    private function quote(string $slug, string $arrival, string $departure, int $guests): array
    {
        $this->client->request(
            'GET',
            sprintf('/api/accommodations/%s/quote?arrival=%s&departure=%s&guests=%d', $slug, $arrival, $departure, $guests),
            server: ['HTTP_ACCEPT' => 'application/ld+json'],
        );

        self::assertResponseIsSuccessful();

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

        return $body;
    }
}
