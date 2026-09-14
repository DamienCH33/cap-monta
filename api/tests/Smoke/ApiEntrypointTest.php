<?php

declare(strict_types=1);

namespace App\Tests\Smoke;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Test de fumée : le kernel démarre et API Platform répond.
 * À supprimer quand les tests fonctionnels des vraies ressources le couvriront.
 */
final class ApiEntrypointTest extends WebTestCase
{
    public function testLePointDEntreeApiRepondEnJsonLd(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api', server: ['HTTP_ACCEPT' => 'application/ld+json']);

        self::assertResponseIsSuccessful();
        self::assertStringStartsWith(
            'application/ld+json',
            (string) $client->getResponse()->headers->get('content-type'),
        );
    }
}
