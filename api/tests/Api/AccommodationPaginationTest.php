<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Factory\AccommodationFactory;
use App\State\StayQuery;
use App\Tests\ApiTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class AccommodationPaginationTest extends ApiTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
    }

    public function testTheFirstPageIsFullAndAnnouncesTheTotal(): void
    {
        $this->createAccommodations(30);

        $body = $this->fetch('/api/accommodations');

        self::assertCount(StayQuery::PER_PAGE, $body['member']);
        self::assertSame(30, $body['totalItems']);
    }

    public function testTheSecondPageContinuesTheSameOrder(): void
    {
        $this->createAccommodations(30);

        $first = $this->fetch('/api/accommodations');
        $second = $this->fetch('/api/accommodations?page=2');

        self::assertCount(30 - StayQuery::PER_PAGE, $second['member']);
        self::assertSame(30, $second['totalItems']);

        // Aucun logement ne doit apparaître deux fois, ni manquer entre les deux pages.
        $slugs = [...array_column($first['member'], 'slug'), ...array_column($second['member'], 'slug')];
        self::assertSame(30, count(array_unique($slugs)));
        self::assertSame($slugs, array_values(array_unique($slugs)));
    }

    public function testAPageBeyondTheLastOneIsEmpty(): void
    {
        $this->createAccommodations(30);

        $body = $this->fetch('/api/accommodations?page=9');

        self::assertSame([], $body['member']);
        self::assertSame(30, $body['totalItems']);
    }

    public function testPageZeroIsRejected(): void
    {
        $this->client->request('GET', '/api/accommodations?page=0', server: ['HTTP_ACCEPT' => 'application/ld+json']);

        // 422 si API Platform refuse la valeur d'après le schéma, 400 si c'est StayQuery.
        self::assertContains($this->client->getResponse()->getStatusCode(), [400, 422]);
    }

    private function createAccommodations(int $count): void
    {
        // Slugs numérotés : l'ordre par défaut est alphabétique, donc connu d'avance.
        AccommodationFactory::createMany($count, static fn (int $i): array => [
            'slug' => sprintf('logement-%02d', $i),
        ]);
    }

    /**
     * @return array{member: list<array{slug: string}>, totalItems: int}
     */
    private function fetch(string $url): array
    {
        $this->client->request('GET', $url, server: ['HTTP_ACCEPT' => 'application/ld+json']);

        self::assertResponseIsSuccessful();

        /** @var array{member: list<array{slug: string}>, totalItems: int} $body */
        $body = json_decode((string) $this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

        return $body;
    }
}
