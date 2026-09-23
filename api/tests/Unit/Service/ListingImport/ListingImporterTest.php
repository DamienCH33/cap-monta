<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\ListingImport;

use App\Enum\Amenity;
use App\Service\ListingImport\ContactDetector;
use App\Service\ListingImport\ListingImporter;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Test\InMemoryPlatform;

/**
 * No network here: InMemoryPlatform stands in for Mistral and returns what the test scripts.
 */
final class ListingImporterTest extends TestCase
{
    private const string PROMPT = __DIR__.'/../../../../config/prompts/listing-import.md';
    private const string TEXT = "Bungalow quartier hawai, clim. Juillet 800 €/semaine, du 20/07 au 10/08 900 €/semaine.\nTél 06 11 22 33 44";

    public function testTheModelAnswerIsCheckedAndCompletedByThePhp(): void
    {
        $modelName = null;
        $input = null;
        $options = [];
        $platform = new InMemoryPlatform(function (Model $model, array|string|object $sentInput, array $sentOptions) use (&$modelName, &$input, &$options): string {
            $modelName = $model->getName();
            $input = $sentInput;
            $options = $sentOptions;

            return json_encode([
                'periods' => [
                    ['label' => 'juillet', 'start' => '2026-07-01', 'end' => '2026-08-01', 'prices' => [['amount' => 800, 'unit' => 'week']], 'minimumNights' => null, 'saturdayArrival' => false],
                    ['label' => 'du 20/07 au 10/08', 'start' => '2026-07-20', 'end' => '2026-08-10', 'prices' => [['amount' => 900, 'unit' => 'week']], 'minimumNights' => null, 'saturdayArrival' => false],
                ],
                'unavailable' => [],
                'questions' => [],
                'listing' => [
                    'type' => 'bungalow', 'capacity' => null, 'bedrooms' => null, 'surface' => null,
                    'district' => 'hawai', 'amenities' => ['climatisation'], 'petsPolicy' => null, 'otherFeatures' => [],
                ],
            ], \JSON_THROW_ON_ERROR);
        });

        $result = $this->importer($platform)->import(self::TEXT, new \DateTimeImmutable('2026-03-01'), ['Europa', 'Hawaï'], 'modele-test');

        self::assertTrue($result->succeeded());
        self::assertSame('modele-test', $modelName);
        self::assertTrue($options['response_format']['json_schema']['strict'], 'strict schema sent');
        self::assertInstanceOf(MessageBag::class, $input);
        self::assertStringContainsString('Date de publication de l\'annonce : 2026-03-01', (string) $input->getUserMessage()?->asText());
        self::assertStringContainsString('Tél [téléphone]', (string) $input->getUserMessage()?->asText(), 'the phone never leaves the server');
        self::assertStringNotContainsString('06 11 22 33 44', (string) $input->getUserMessage()?->asText());
        self::assertStringContainsString('Tu LIS, tu ne devines rien', (string) $input->getSystemMessage()?->getContent());

        $extraction = $result->extraction;
        self::assertNotNull($extraction);
        self::assertCount(2, $extraction->periods);
        self::assertSame(['« juillet » et « du 20/07 au 10/08 » se chevauchent : une des deux est-elle déjà louée ?'], $extraction->questions, 'added by the PHP, not the model');
        self::assertSame('Hawaï', $extraction->listing?->district, 'matched to the known name');
        self::assertSame([Amenity::AirConditioning], $extraction->listing->amenities);
        self::assertSame(['06 11 22 33 44'], $result->contacts);
    }

    public function testAPlatformFailureIsAResultNotACrash(): void
    {
        $platform = new InMemoryPlatform(static fn (): never => throw new RuntimeException('Mistral ne répond pas'));

        $result = $this->importer($platform)->import(self::TEXT, new \DateTimeImmutable('2026-03-01'), []);

        self::assertFalse($result->succeeded());
        self::assertSame('Mistral ne répond pas', $result->error);
        self::assertSame(ListingImporter::DEFAULT_MODEL, $result->model);
        self::assertSame(['06 11 22 33 44'], $result->contacts, 'the contact check does not need the model');
    }

    public function testAnUnreadableAnswerIsAFailure(): void
    {
        $notJson = $this->importer(new InMemoryPlatform('Voici les tarifs : juillet 800 €'))->import(self::TEXT, new \DateTimeImmutable(), []);
        $wrongShape = $this->importer(new InMemoryPlatform('{"periods": "juillet"}'))->import(self::TEXT, new \DateTimeImmutable(), []);

        self::assertFalse($notJson->succeeded());
        self::assertFalse($wrongShape->succeeded());
        self::assertSame('{"periods": "juillet"}', $wrongShape->rawOutput, 'kept for the diagnosis');
    }

    public function testADistrictTheSiteDoesNotKnowIsDropped(): void
    {
        $platform = new InMemoryPlatform((string) json_encode([
            'periods' => [], 'unavailable' => [], 'questions' => [],
            'listing' => ['district' => 'Tahiti', 'amenities' => []],
        ]));

        $listing = $this->importer($platform)->import('Bungalow à Tahiti', new \DateTimeImmutable(), ['Europa'])->extraction?->listing;

        self::assertNull($listing?->district);
        self::assertSame(['quartier inconnu : Tahiti'], $listing?->rejected);
    }

    private function importer(InMemoryPlatform $platform): ListingImporter
    {
        return new ListingImporter($platform, new ContactDetector(), new NullLogger(), self::PROMPT);
    }
}
