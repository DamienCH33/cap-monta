<?php

declare(strict_types=1);

namespace App\Service\ListingImport;

use App\Enum\Amenity;
use App\Enum\PriceUnit;

/**
 * The PHP proofreads the model's answer before the owner sees it. Everything here is a rule
 * that can be checked for sure, so it is done in code rather than asked to the model
 * (evaluation of 23/09: the model read prices well and got these wrong):
 *
 * - a label made only of months gets its dates from MonthLabel, and "juin et septembre" becomes
 *   two periods;
 * - a unit the text never mentions ("stay" with no stay price anywhere) becomes unknown;
 * - "7 nights minimum" on a Saturday-to-Saturday rental says nothing more: dropped;
 * - the same rate read twice (the title repeats the body) is kept once, and dates without any
 *   price ("Disponibilités : du 18 au 25 juillet") are not a rate: dropped;
 * - an equipment the text does not name is unticked (AmenityEvidence); one the model filed
 *   under "other features" although it is in the list ("grande terrasse") is ticked;
 * - a covered terrace is a terrace.
 */
final class ExtractionReview
{
    /**
     * Before the checks of ListingExtraction::fromArray(): a date written "07-01" (no year) is
     * completed with the publication year instead of failing the whole import.
     *
     * @param array<mixed> $data
     *
     * @return array<mixed>
     */
    public static function repairDates(array $data, \DateTimeImmutable $publishedAt): array
    {
        $repair = static fn (mixed $date): mixed => \is_string($date) && 1 === preg_match('/^\d\d-\d\d$/', $date)
            ? $publishedAt->format('Y').'-'.$date
            : $date;

        foreach (['periods', 'unavailable'] as $list) {
            if (!\is_array($data[$list] ?? null)) {
                continue;
            }
            foreach ($data[$list] as $i => $item) {
                if (\is_array($item)) {
                    $item['start'] = $repair($item['start'] ?? null);
                    $item['end'] = $repair($item['end'] ?? null);
                    $data[$list][$i] = $item;
                }
            }
        }

        return $data;
    }

    /** Words that must be somewhere in the text for a unit to be believed. */
    private const array UNIT_WORDS = [
        'week' => '/ (?:semaines?|sem|hebdo\w*) /',
        'night' => '/ (?:nuits?|nuitees?) /',
        'stay' => '/ (?:(?:pour|les) \d+ (?:semaines|nuits|jours)|quinzaine|au total|par sejour|le sejour complet|tout le sejour) /',
    ];

    public static function apply(ListingExtraction $extraction, string $text, \DateTimeImmutable $publishedAt): ListingExtraction
    {
        $words = AmenityEvidence::words($text);
        $periods = [];
        foreach ($extraction->periods as $period) {
            $period = new ExtractedPeriod(
                $period->label, $period->start, $period->end,
                array_map(static fn (ExtractedPrice $price): ExtractedPrice => self::checkedUnit($price, $words), $period->prices),
                $period->minimumNights, $period->saturdayArrival,
            );
            foreach (self::datedFromLabel($period, (int) $publishedAt->format('Y')) as $dated) {
                $periods[] = $dated;
            }
        }

        return new ListingExtraction(
            self::withoutDuplicates($periods),
            $extraction->unavailable,
            [], // the questions are the PHP's (ExtractionRules), asked after this review
            null === $extraction->listing ? null : self::checkedListing($extraction->listing, $text),
        );
    }

    /**
     * @return list<ExtractedPeriod>
     */
    private static function datedFromLabel(ExtractedPeriod $period, int $year): array
    {
        $minimumNights = $period->saturdayArrival && 7 === $period->minimumNights ? null : $period->minimumNights;
        $months = MonthLabel::periods($period->label, $year);

        if (null === $months) {
            return [new ExtractedPeriod($period->label, $period->start, $period->end, $period->prices, $minimumNights, $period->saturdayArrival)];
        }

        return array_map(
            static fn (array $run): ExtractedPeriod => new ExtractedPeriod(
                1 === \count($months) ? $period->label : $run['label'],
                $run['start'],
                $run['end'],
                $period->prices,
                $minimumNights,
                $period->saturdayArrival,
            ),
            $months,
        );
    }

    /**
     * A period without a price is not a rate; one that repeats another (the same prices, without
     * dates) adds nothing.
     *
     * @param list<ExtractedPeriod> $periods
     *
     * @return list<ExtractedPeriod>
     */
    private static function withoutDuplicates(array $periods): array
    {
        $prices = static function (ExtractedPeriod $period): string {
            $keys = array_map(static fn (ExtractedPrice $price): string => $price->key(), $period->prices);
            sort($keys);

            return implode(',', $keys);
        };
        $dated = static fn (ExtractedPeriod $period): bool => null !== $period->start || null !== $period->end;
        $kept = [];
        $seen = [];

        foreach ($periods as $i => $period) {
            if ([] === $period->prices || isset($seen[$period->key()])) {
                continue;
            }
            foreach ($periods as $j => $other) {
                if ($i !== $j && !$dated($period) && $dated($other) && $prices($period) === $prices($other)) {
                    continue 2;
                }
            }
            $kept[] = $period;
            $seen[$period->key()] = true;
        }

        return $kept;
    }

    private static function checkedUnit(ExtractedPrice $price, string $words): ExtractedPrice
    {
        $pattern = self::UNIT_WORDS[$price->unit->value] ?? null;

        return null === $pattern || 1 === preg_match($pattern, $words) ? $price : new ExtractedPrice($price->amount, PriceUnit::Unknown);
    }

    private static function checkedListing(ExtractedListing $listing, string $text): ExtractedListing
    {
        $amenities = array_values(array_filter(
            $listing->amenities,
            static fn (Amenity $amenity): bool => AmenityEvidence::isWritten($amenity, $text),
        ));

        // Filed under "other features" but in the list: ticked, if the listing itself says so.
        foreach (Amenity::cases() as $amenity) {
            if (\in_array($amenity, $amenities, true)) {
                continue;
            }
            foreach ($listing->otherFeatures as $feature) {
                if (AmenityEvidence::isWritten($amenity, $feature) && AmenityEvidence::isWritten($amenity, $text)) {
                    $amenities[] = $amenity;
                    break;
                }
            }
        }

        if (\in_array(Amenity::CoveredTerrace, $amenities, true) && !\in_array(Amenity::Terrace, $amenities, true)) {
            $amenities[] = Amenity::Terrace;
        }

        return new ExtractedListing(
            $listing->type, $listing->capacity, $listing->bedrooms, $listing->surface, $listing->district,
            $amenities, $listing->petsPolicy, $listing->otherFeatures, $listing->rejected,
        );
    }
}
