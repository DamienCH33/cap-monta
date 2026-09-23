<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HealthApiTest extends WebTestCase
{
    public function testAnonymousCallersOnlyGetTheOverallState(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/health');

        self::assertResponseIsSuccessful();
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($body);
        self::assertSame(['status'], array_keys($body));
    }

    public function testTheTokenGivesTheStateOfEachComponent(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/health', server: ['HTTP_X_HEALTH_TOKEN' => 'secret-de-test']);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Cache-Control', 'no-store, private');

        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($body);
        self::assertContains($body['status'], ['ok', 'degraded']);
        self::assertSame('ok', $body['checks']['database']);
        self::assertSame(['database', 'cache', 'worker', 'assistant'], array_keys($body['checks']));
    }
}
