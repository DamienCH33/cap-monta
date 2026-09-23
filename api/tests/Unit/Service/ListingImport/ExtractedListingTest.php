<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\ListingImport;

use App\Enum\AccommodationType;
use App\Enum\Amenity;
use App\Enum\PetsPolicy;
use App\Service\ListingImport\ContactDetector;
use App\Service\ListingImport\Evaluation\EvalCase;
use App\Service\ListingImport\Evaluation\ExtractionScorer;
use App\Service\ListingImport\ExtractedListing;
use App\Service\ListingImport\ListingExtraction;
use PHPUnit\Framework\TestCase;

final class ExtractedListingTest extends TestCase
{
    public function testWhatTheModelReturnsIsSortedIntoFourBoxes(): void
    {
        $listing = ExtractedListing::fromArray([
            'type' => 'bungalow',
            'capacity' => 6,
            'amenities' => ['climatisation', 'jacuzzi', 'climatisation', 'plancha'],
            'petsPolicy' => 'not_allowed',
            'otherFeatures' => ['hamac', ' ', 'transats'],
        ]);

        self::assertSame(AccommodationType::Bungalow, $listing->type);
        self::assertSame([Amenity::AirConditioning, Amenity::Plancha], $listing->amenities, '1. known keys, once each');
        self::assertSame(['hamac', 'transats'], $listing->otherFeatures, '2. out of the list, kept for the owner');
        self::assertSame(PetsPolicy::NotAllowed, $listing->petsPolicy, '3. a house rule in its own field');
        self::assertSame(['équipement hors liste : jacuzzi'], $listing->rejected, '4. an invented key is never stored');
    }

    public function testAnImpossibleValueIsDroppedWithoutLosingTheRest(): void
    {
        $listing = ExtractedListing::fromArray([
            'type' => 'villa',
            'capacity' => 40,
            'bedrooms' => 3,
            'surface' => '40 m²',
            'petsPolicy' => 'maybe',
        ]);

        self::assertNull($listing->type);
        self::assertNull($listing->capacity);
        self::assertSame(3, $listing->bedrooms);
        self::assertNull($listing->surface);
        self::assertNull($listing->petsPolicy);
        self::assertCount(4, $listing->rejected);
    }

    public function testNothingWrittenMeansNothingFilled(): void
    {
        $listing = ExtractedListing::fromArray([]);

        self::assertNull($listing->capacity);
        self::assertNull($listing->petsPolicy, 'no default here: the form applies its own');
        self::assertSame([], $listing->amenities);
    }

    public function testTheScorerJudgesTheAccommodationForm(): void
    {
        $case = EvalCase::fromArray([
            'id' => 'form',
            'text' => 'Bungalow 3 chambres. Clim, hamac. Table et salon sur la terrasse.',
            'publishedAt' => '2026-03-01',
            'expected' => [
                'periods' => [],
                'unavailable' => [],
                'needsClarification' => true,
                'listing' => [
                    'type' => 'bungalow',
                    'bedrooms' => 3,
                    'amenities' => ['climatisation', 'terrasse'],
                    'optionalAmenities' => ['salon-de-jardin'],
                    'otherFeatures' => ['hamac'],
                ],
            ],
        ]);

        $good = ListingExtraction::fromArray([
            'periods' => [],
            'unavailable' => [],
            'questions' => ['Quels sont vos tarifs ?'],
            'listing' => [
                'type' => 'bungalow',
                'bedrooms' => 3,
                'amenities' => ['climatisation', 'terrasse', 'salon-de-jardin'],
                'otherFeatures' => ['un hamac'],
            ],
        ]);
        $bad = ListingExtraction::fromArray([
            'periods' => [],
            'unavailable' => [],
            'questions' => ['Quels sont vos tarifs ?'],
            'listing' => ['type' => 'mobile_home', 'bedrooms' => 3, 'capacity' => 6, 'amenities' => ['climatisation', 'wifi']],
        ]);

        $scorer = new ExtractionScorer();
        self::assertSame([], $scorer->score($case, $good)->problems(), 'optional amenity tolerated, "un hamac" reports "hamac"');

        $score = $scorer->score($case, $bad);
        self::assertSame(['wifi'], $score->inventedAmenities);
        self::assertSame(['terrasse'], $score->missedAmenities);
        self::assertSame(['hamac'], $score->lostFeatures);
        self::assertSame([
            'type : attendu bungalow, trouvé mobile_home',
            'capacité : attendu rien, trouvé 6',
        ], $score->wrongFields);
    }

    public function testContactDetailsInTheTextAreFound(): void
    {
        $found = new ContactDetector()->find(
            "Appelez le 06 12 34 56 78 ou le +33 6.12.34.56.79, écrivez à jean.dupont@exemple.fr.\n"
            .'Emplacement 105, plage à 150 m, 2026-07-04, tarif 1200 €.',
        );

        self::assertSame(['06 12 34 56 78', '+33 6.12.34.56.79', 'jean.dupont@exemple.fr'], $found);
    }
}
