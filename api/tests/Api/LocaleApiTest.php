<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Factory\AccommodationFactory;
use App\Tests\ApiTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * The API answers in the language of the page (Accept-Language), and never translates what
 * people wrote themselves.
 */
final class LocaleApiTest extends ApiTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testAnErrorComesBackInTheLanguageOfThePage(): void
    {
        $this->client->request('POST', '/api/booking-requests/recover', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT_LANGUAGE' => 'de',
            'HTTP_ORIGIN' => 'http://localhost',
        ], content: '{"email":"pas-une-adresse"}');

        self::assertResponseStatusCodeSame(422);
        /** @var array{detail: string, violations: list<array{message: string}>} $body */
        $body = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame('Diese E-Mail-Adresse scheint ungültig zu sein.', $body['detail']);
        self::assertSame('Diese E-Mail-Adresse scheint ungültig zu sein.', $body['violations'][0]['message']);
    }

    public function testAFrenchPageGetsTheFrenchMessage(): void
    {
        $this->client->request('POST', '/api/booking-requests/recover', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT_LANGUAGE' => 'fr-FR,fr;q=0.9',
            'HTTP_ORIGIN' => 'http://localhost',
        ], content: '{"email":"pas-une-adresse"}');

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('Cette adresse email ne semble pas valide.', (string) $this->client->getResponse()->getContent());
    }

    public function testWhatAnOwnerWroteIsNeverTranslated(): void
    {
        // One line of this description is, word for word, a message of the site.
        $description = "Joli mobil-home au calme, à deux pas de la plage et des commerces.\nAjoutez au moins une photo.";
        AccommodationFactory::createOne(['slug' => 'mobil-home-calme', 'description' => $description]);

        $this->client->request('GET', '/api/accommodations/mobil-home-calme', server: ['HTTP_ACCEPT_LANGUAGE' => 'en']);

        self::assertResponseIsSuccessful();
        /** @var array{description: string} $body */
        $body = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame($description, $body['description']);
        // A shared cache must not hand the English answer to a French page.
        self::assertContains('Accept-Language', $this->client->getResponse()->getVary());
    }
}
