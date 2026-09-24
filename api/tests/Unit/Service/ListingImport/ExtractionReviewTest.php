<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\ListingImport;

use App\Enum\Amenity;
use App\Enum\PriceUnit;
use App\Service\ListingImport\AmenityEvidence;
use App\Service\ListingImport\ExtractionReview;
use App\Service\ListingImport\ExtractionRules;
use App\Service\ListingImport\ListingExtraction;
use App\Service\ListingImport\MonthLabel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * What the PHP corrects in the model's answer, without the model.
 */
final class ExtractionReviewTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function labels(): iterable
    {
        yield 'one month' => ['septembre', '2026-09-01→2026-10-01'];
        yield 'two consecutive months' => ['Juillet et Août (haute saison)', '2026-07-01→2026-09-01'];
        yield 'two separate months' => ['Juin et septembre', '2026-06-01→2026-07-01 | 2026-09-01→2026-10-01'];
        yield 'a range and a year' => ['juin à septembre 2027', '2027-06-01→2027-10-01'];
        yield 'a price copied in the label' => ['avril / mai 400 € par semaine', '2026-04-01→2026-06-01'];
        yield 'a two-digit year' => ['septembre 26', '2026-09-01→2026-10-01'];
        yield 'an earlier availability' => ['Septembre 2026 (disponible à partir du 29 août)', '2026-08-29→2026-10-01'];
        yield 'over new year' => ['novembre à février', '2026-11-01→2027-03-01'];
        yield 'abbreviations' => ['Mai/Juin/Sept 2024', '2024-05-01→2024-07-01 | 2024-09-01→2024-10-01'];
        yield 'vague' => ['Mi-juin / 11 juillet 2026', null];
        yield 'a season' => ['hors saison', null];
        yield 'days' => ['du 4 au 11 juillet', null];
    }

    #[DataProvider('labels')]
    public function testMonthLabelsGetTheirDatesFromThePhp(string $label, ?string $expected): void
    {
        $periods = MonthLabel::periods($label, 2026);

        self::assertSame($expected, null === $periods ? null : implode(' | ', array_map(
            static fn (array $p): string => $p['start']->format('Y-m-d').'→'.$p['end']->format('Y-m-d'),
            $periods,
        )));
    }

    public function testAnEquipmentMustBeWrittenAndNotDenied(): void
    {
        self::assertTrue(AmenityEvidence::isWritten(Amenity::AirConditioning, 'Mobilhome tout confort climatisé'));
        self::assertTrue(AmenityEvidence::isWritten(Amenity::OutdoorShower, 'Douches intérieure et extérieure'));
        self::assertTrue(AmenityEvidence::isWritten(Amenity::Terrace, 'Chiens pas acceptés. Grande terrasse couverte'));
        self::assertFalse(AmenityEvidence::isWritten(Amenity::Wifi, 'Bungalow tout confort'));
        self::assertFalse(AmenityEvidence::isWritten(Amenity::Television, 'Pas de téléviseur.'));
        self::assertFalse(AmenityEvidence::isWritten(Amenity::LinenProvided, 'Linge (draps, serviettes) non fourni'));
        self::assertFalse(AmenityEvidence::isWritten(Amenity::Bikes, 'local à vélos'));
        self::assertTrue(AmenityEvidence::isWritten(Amenity::Bikes, 'Garage à vélos et vélos à disposition'));
    }

    public function testTheModelAnswerIsProofread(): void
    {
        $text = "Bungalow tout confort, grande terrasse couverte, chauffage.\nJuin et septembre : 700 € la semaine. Du samedi au samedi.\nDisponible du 4 au 11 juillet.";
        $answer = ExtractionReview::repairDates([
            'periods' => [
                ['label' => 'Juin et septembre', 'start' => null, 'end' => null, 'prices' => [['amount' => 700, 'unit' => 'week']], 'minimumNights' => 7, 'saturdayArrival' => true],
                ['label' => 'juin', 'start' => null, 'end' => null, 'prices' => [['amount' => 700, 'unit' => 'stay']], 'minimumNights' => null, 'saturdayArrival' => true],
                ['label' => 'du 4 au 11 juillet', 'start' => '07-04', 'end' => '07-11', 'prices' => [], 'minimumNights' => null, 'saturdayArrival' => true],
            ],
            'unavailable' => [],
            'questions' => ['Le ménage est-il compris ?'],
            'listing' => ['amenities' => ['wifi', 'chauffage', 'terrasse-couverte'], 'otherFeatures' => []],
        ], new \DateTimeImmutable('2026-05-10'));

        $reviewed = ExtractionReview::apply(ListingExtraction::fromArray($answer), $text, new \DateTimeImmutable('2026-05-10'));

        self::assertSame([
            '2026-06-01→2026-07-01 [700/week] min=- samedi',
            '2026-09-01→2026-10-01 [700/week] min=- samedi',
            '2026-06-01→2026-07-01 [700/unknown] min=- samedi',
        ], array_map(static fn ($p): string => $p->key(), $reviewed->periods), 'split, dated, "stay" not written, dates without price dropped');
        self::assertSame(PriceUnit::Unknown, $reviewed->periods[2]->prices[0]->unit);
        self::assertSame([], $reviewed->questions, 'the model\'s questions are not kept');
        self::assertSame([Amenity::Heating, Amenity::CoveredTerrace, Amenity::Terrace], $reviewed->listing?->amenities);
    }

    public function testFloorsRangesDiscountsAndTheBarePriceFieldAreNotRates(): void
    {
        $text = "Bungalow 4-6 personnes. Loue 560-840 euros. Juillet : 1050 € / semaine. Dégressif : 2 950 € les 3 semaines. Hors saison à partir de 350 € la semaine.\nPrix : 630€";
        $period = static fn (string $label, ?string $start, ?string $end, int $amount, string $unit): array => [
            'label' => $label, 'start' => $start, 'end' => $end, 'prices' => [['amount' => $amount, 'unit' => $unit]], 'minimumNights' => null, 'saturdayArrival' => false,
        ];

        $reviewed = ExtractionReview::apply(ListingExtraction::fromArray([
            'periods' => [
                $period('juillet', '2026-07-01', '2026-08-01', 1050, 'week'),
                $period('juillet', '2026-07-01', '2026-08-01', 2950, 'stay'),
                $period('hors saison', null, null, 350, 'week'),
                $period('été', null, null, 560, 'unknown'),
                $period('annonce', null, null, 630, 'unknown'),
            ],
            'unavailable' => [],
            'listing' => ['capacity' => 6, 'amenities' => []],
        ]), $text, new \DateTimeImmutable('2026-05-10'));

        self::assertSame(['2026-07-01→2026-08-01 [1050/week] min=-'], array_map(static fn ($p): string => $p->key(), $reviewed->periods));
        self::assertNull($reviewed->listing?->capacity, '"4-6 personnes" is a range, 6 was a guess');
    }

    public function testTheCapacityIsTheNumberWritten(): void
    {
        $reviewed = ExtractionReview::apply(
            ListingExtraction::fromArray(['periods' => [], 'unavailable' => [], 'listing' => ['capacity' => null, 'amenities' => []]]),
            '6 couchages (3 adultes maximum)',
            new \DateTimeImmutable('2026-05-10'),
        );

        self::assertSame(6, $reviewed->listing?->capacity);
    }

    public function testAUnitMustBeWrittenNextToItsAmount(): void
    {
        $text = 'Juillet : 800 € la semaine et du 20 au 31 août (1200 €). Caution 300 €.';
        $reviewed = $this->review($text, [
            self::period('juillet', '2026-07-01', '2026-08-01', 800, 'week'),
            self::period('du 20 au 31 août', '2026-08-20', '2026-08-31', 1200, 'week'),
        ]);

        self::assertSame(['800/week', '1200/unknown'], $this->prices($reviewed));

        $heading = $this->review("Tarifs à la semaine :\nJuin : 450 €\nJuillet : 650 €", [
            self::period('juin', null, null, 450, 'week'),
        ]);
        self::assertSame(['450/week'], $this->prices($heading), 'a heading gives the unit to the list below');
    }

    public function testAConditionalPriceIsNotARate(): void
    {
        $text = "Mai : 65 €/nuit ou 450 €/semaine ou 400 €/si 2 semaines.\nÉté : 900 € la semaine (850 € à partir de 15 jours).\nJuin : 500 € la semaine à partir du 15 juin.";
        $reviewed = $this->review($text, [
            ['label' => 'mai', 'start' => null, 'end' => null, 'prices' => [['amount' => 65, 'unit' => 'night'], ['amount' => 450, 'unit' => 'week'], ['amount' => 400, 'unit' => 'unknown']], 'minimumNights' => null, 'saturdayArrival' => false],
            ['label' => 'été', 'start' => null, 'end' => null, 'prices' => [['amount' => 900, 'unit' => 'week'], ['amount' => 850, 'unit' => 'week']], 'minimumNights' => null, 'saturdayArrival' => false],
            self::period('juin', null, null, 500, 'week'),
        ]);

        self::assertSame(['65/night', '450/week', '900/week', '500/week'], $this->prices($reviewed));
    }

    public function testTheSurfaceIsTheLivingAreaAndBoilersDoNotHeat(): void
    {
        $text = 'Mobil-home de 32 m2 sur une parcelle de 100 m2. Chauffe-eau gaz. Machine à laver la vaisselle et linge. Douche extérieure.';
        $parcel = $this->reviewListing($text, ['surface' => 100, 'amenities' => ['chauffage']]);
        $living = $this->reviewListing($text, ['surface' => 32, 'amenities' => []]);
        self::assertNotNull($parcel);
        self::assertNotNull($living);

        self::assertNull($parcel->surface);
        self::assertSame(32, $living->surface);
        self::assertNotContains(Amenity::Heating, $parcel->amenities);
        self::assertSame(
            [Amenity::Dishwasher, Amenity::WashingMachine, Amenity::OutdoorShower],
            array_values(array_filter($living->amenities, static fn (Amenity $a): bool => \in_array($a, [Amenity::Dishwasher, Amenity::WashingMachine, Amenity::OutdoorShower], true))),
            'written without doubt: ticked even when the model forgot them',
        );
    }

    public function testOneWrongLineIsDroppedNotTheWholeReading(): void
    {
        [$data, $dropped] = ExtractionReview::dropInvalid([
            'periods' => [
                ['label' => 'hors juillet/août', 'start' => '2026-09-01', 'end' => '2026-06-30', 'prices' => [['amount' => 600, 'unit' => 'week']], 'minimumNights' => 7, 'saturdayArrival' => false],
                ['label' => 'semaine', 'start' => null, 'end' => null, 'prices' => [['amount' => 0, 'unit' => 'week'], ['amount' => 600, 'unit' => 'week']], 'minimumNights' => 0, 'saturdayArrival' => false],
            ],
            'unavailable' => [['start' => '2026-07-01', 'end' => null]],
        ]);

        self::assertSame(['hors juillet/août', 'des dates indisponibles'], $dropped);
        self::assertSame([['amount' => 600, 'unit' => 'week']], $data['periods'][0]['prices']);
        self::assertNull($data['periods'][0]['minimumNights']);
        self::assertSame([], $data['unavailable']);
        ListingExtraction::fromArray($data); // readable now
    }

    public function testRatesOfASeasonAlreadyOverAskAQuestion(): void
    {
        $extraction = ListingExtraction::fromArray(['periods' => [self::period('avril 2024', '2024-04-01', '2024-05-01', 375, 'week')], 'unavailable' => []]);

        self::assertSame([], ExtractionRules::questions($extraction));
        self::assertStringContainsString('2024', ExtractionRules::questions($extraction, new \DateTimeImmutable('2026-05-04'))[0]);
    }

    /**
     * @param list<array<string, mixed>> $periods
     */
    private function review(string $text, array $periods): ListingExtraction
    {
        return ExtractionReview::apply(ListingExtraction::fromArray(['periods' => $periods, 'unavailable' => []]), $text, new \DateTimeImmutable('2026-05-10'));
    }

    /**
     * @param array<string, mixed> $listing
     */
    private function reviewListing(string $text, array $listing): ?\App\Service\ListingImport\ExtractedListing
    {
        return ExtractionReview::apply(ListingExtraction::fromArray(['periods' => [], 'unavailable' => [], 'listing' => $listing]), $text, new \DateTimeImmutable('2026-05-10'))->listing;
    }

    /**
     * @return list<string>
     */
    private function prices(ListingExtraction $extraction): array
    {
        $keys = [];
        foreach ($extraction->periods as $period) {
            foreach ($period->prices as $price) {
                $keys[] = $price->key();
            }
        }

        return $keys;
    }

    /**
     * @return array<string, mixed>
     */
    private static function period(string $label, ?string $start, ?string $end, int $amount, string $unit): array
    {
        return ['label' => $label, 'start' => $start, 'end' => $end, 'prices' => [['amount' => $amount, 'unit' => $unit]], 'minimumNights' => null, 'saturdayArrival' => false];
    }
}
