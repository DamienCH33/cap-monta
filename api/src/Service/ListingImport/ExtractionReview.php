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
 * - a covered terrace is a terrace;
 * - a price the text only gives as a floor, a range or a discount ("à partir de 350 €",
 *   "560-840 €", "dégressif : 2 950 € les 3 semaines") is not a rate, and a price with neither dates nor unit cannot
 *   become one: both dropped;
 * - the capacity is the number written before "personnes" or "couchages", never a guess.
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
        $amounts = self::amountWords($text);
        $periods = [];
        foreach ($extraction->periods as $period) {
            $prices = array_values(array_filter(
                array_map(static fn (ExtractedPrice $price): ExtractedPrice => self::checkedUnit($price, $words), $period->prices),
                static fn (ExtractedPrice $price): bool => !self::isOnlyFloorOrDiscount($price->amount, $amounts),
            ));
            $period = new ExtractedPeriod($period->label, $period->start, $period->end, $prices, $period->minimumNights, $period->saturdayArrival);
            // A label without a year takes the one the model read ("Disponibilités 2027 : juin").
            $year = (int) ($period->start ?? $period->end ?? $publishedAt)->format('Y');
            foreach (self::datedFromLabel($period, $year) as $dated) {
                $periods[] = $dated;
            }
        }

        return new ListingExtraction(
            self::withoutDuplicates($periods, $amounts),
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
     * dates) adds nothing; a price only found in the site's "Prix :" field, without dates or
     * unit, cannot be placed anywhere.
     *
     * @param list<ExtractedPeriod> $periods
     *
     * @return list<ExtractedPeriod>
     */
    private static function withoutDuplicates(array $periods, string $amounts): array
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
            // "Prix : 630 €" and nothing else: no dates, no unit, not even a line of the text.
            $priceFieldOnly = [] === array_filter(
                $period->prices,
                static fn (ExtractedPrice $price): bool => PriceUnit::Unknown !== $price->unit || !self::onlyInPriceField($price->amount, $amounts),
            );
            if ([] === $period->prices || isset($seen[$period->key()]) || (!$dated($period) && $priceFieldOnly)) {
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

    /** Words just before an amount that make it a floor or a discount, not a rate. */
    private const string FLOOR_OR_DISCOUNT = '/(?:\\ba partir de(?: \\w+)?|\\bdes(?: \\w+)?|\\b(?:degressi\\w*|remises?|reductions?)(?: \\w+){0,5})$/';

    /**
     * The text for amounts: lower case, no accents, "2 950" and "1.000" glued back together.
     */
    private static function amountWords(string $text): string
    {
        // Not after a date: "11/07 700 €" is the 11th of July, then 700 €.
        $text = (string) preg_replace('/(?<![\d\/])(\d{1,3})[ .\x{202F}\x{A0}](\d{3})\b/u', '$1$2', MonthLabel::fold($text));

        return ' '.trim((string) preg_replace('/[^a-z0-9]+/', ' ', $text)).' ';
    }

    /**
     * True when every time the amount is written it is a floor or a discount. "Prix : 350 €"
     * (the price field of the site, a copy of the lowest price) counts for nothing either way.
     */
    private static function isOnlyFloorOrDiscount(int $amount, string $amounts): bool
    {
        $marked = false;
        preg_match_all('/(?<= )'.$amount.'(?= )/', $amounts, $found, \PREG_OFFSET_CAPTURE);

        foreach ($found[0] as [, $offset]) {
            $before = implode(' ', \array_slice(explode(' ', trim(substr($amounts, 0, $offset))), -6));
            $after = strtok(substr($amounts, $offset + \strlen((string) $amount)), ' ');
            if (1 === preg_match(self::FLOOR_OR_DISCOUNT, $before)
                || self::isOtherBound(strrchr(' '.$before, ' '), $amount)
                || self::isOtherBound(false === $after ? '' : $after, $amount)) {
                $marked = true;
            } elseif (!str_ends_with($before, 'prix')) {
                return false;
            }
        }

        return $marked;
    }

    /**
     * "560-840 €": a number right next to the amount, of the same kind (not a year, not a day),
     * makes it one bound of a range.
     */
    private static function isOtherBound(string|false $word, int $amount): bool
    {
        $word = trim((string) $word);

        return 1 === preg_match('/^\d+$/', $word) && (int) $word >= 50 && (int) $word < 2000 && (int) $word !== $amount;
    }

    private static function onlyInPriceField(int $amount, string $amounts): bool
    {
        return 0 === preg_match('/(?<!prix )(?<= )'.$amount.'(?= )/', $amounts);
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
            $listing->type, self::writtenCapacity($listing->capacity, $text), $listing->bedrooms, $listing->surface, $listing->district,
            $amenities, $listing->petsPolicy, $listing->otherFeatures, $listing->rejected,
        );
    }

    /**
     * "6 personnes", "5 couchages": one number written, it is the capacity; none, it stays
     * empty; several, the model's pick if it is one of them. A range ("4-6 personnes") is none.
     */
    private static function writtenCapacity(?int $capacity, string $text): ?int
    {
        preg_match_all('/(?<! a)(?<!\d) (\d{1,2}) (?:personnes?|pers|couchages?|places|voyageurs)\b/', self::amountWords($text), $found);
        $written = array_values(array_unique(array_map(intval(...), $found[1])));

        return match (true) {
            1 === \count($written) => $written[0],
            \in_array($capacity, $written, true) => $capacity,
            default => null,
        };
    }
}
