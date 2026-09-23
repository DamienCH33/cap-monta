<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\ListingImport;

use App\Enum\Amenity;
use App\Enum\PriceUnit;
use App\Service\ListingImport\AmenityEvidence;
use App\Service\ListingImport\ExtractionReview;
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
}
