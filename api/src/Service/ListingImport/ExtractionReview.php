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

    /** A heading that gives the unit of the whole list below it. */
    private const array UNIT_HEADINGS = [
        'week' => '/ (?:tarifs?|prix|location|loyers?) (?:\\w+ ){0,2}(?:(?:a la|par|a|en) )?semaines? /',
        'night' => '/ (?:tarifs?|prix|location|loyers?) (?:\\w+ ){0,2}(?:(?:a la|par|a|en) )?nuits? /',
    ];

    /** Words that must be written next to the amount for a unit to be believed. */
    private const array UNIT_WORDS = [
        'week' => '/ (?:semaines?|sem|hebdo\w*) /',
        'night' => '/ (?:nuits?|nuitees?) /',
        'stay' => '/ (?:(?:pour|les) \d+ (?:semaines|nuits|jours)|quinzaine|au total|par sejour|le sejour complet|tout le sejour) /',
    ];

    /**
     * Before ListingExtraction::fromArray(), which refuses any wrong item: one wrong line must not
     * throw away the whole reading (24/09, a real listing: one price "0" and the owner got
     * nothing at all). A price that is not a positive whole number is dropped (0 is never a
     * price); a period or a date range that cannot exist (end before start, unreadable date) is
     * dropped and returned, so that the owner is told a line was not understood.
     *
     * @param array<mixed> $data
     *
     * @return array{0: array<mixed>, 1: list<string>} the data kept, and the labels dropped
     */
    public static function dropInvalid(array $data): array
    {
        $dropped = [];

        if (\is_array($data['periods'] ?? null)) {
            $periods = [];
            foreach ($data['periods'] as $period) {
                if (!\is_array($period)) {
                    continue;
                }
                if (\is_array($period['prices'] ?? null)) {
                    $period['prices'] = array_values(array_filter(
                        $period['prices'],
                        static fn (mixed $price): bool => \is_array($price) && \is_int($price['amount'] ?? null) && $price['amount'] > 0,
                    ));
                }
                if (\is_int($period['minimumNights'] ?? null) && $period['minimumNights'] < 1) {
                    $period['minimumNights'] = null;
                }
                try {
                    ExtractedPeriod::fromArray($period);
                    $periods[] = $period;
                } catch (InvalidExtractionException) {
                    $dropped[] = \is_string($period['label'] ?? null) && '' !== trim($period['label']) ? trim($period['label']) : 'une période';
                }
            }
            $data['periods'] = $periods;
        }

        if (\is_array($data['unavailable'] ?? null)) {
            $ranges = [];
            foreach ($data['unavailable'] as $range) {
                try {
                    ExtractedUnavailability::fromArray(\is_array($range) ? $range : []);
                    $ranges[] = $range;
                } catch (InvalidExtractionException) {
                    $dropped[] = 'des dates indisponibles';
                }
            }
            $data['unavailable'] = $ranges;
        }

        return [$data, array_values(array_unique($dropped))];
    }

    public static function apply(ListingExtraction $extraction, string $text, \DateTimeImmutable $publishedAt): ListingExtraction
    {
        $words = AmenityEvidence::words($text);
        $amounts = self::amountWords($text);
        $periods = [];
        foreach ($extraction->periods as $period) {
            $prices = array_values(array_filter(
                array_map(static fn (ExtractedPrice $price): ExtractedPrice => self::checkedUnit($price, $words, $amounts), $period->prices),
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
            array_map(self::wholeMonth(...), self::withoutDuplicates($periods, $amounts)),
            array_values(array_map(
                static fn (ExtractedUnavailability $range): ExtractedUnavailability => new ExtractedUnavailability($range->start, self::monthEnd($range->end)),
                array_filter($extraction->unavailable, static fn (ExtractedUnavailability $range): bool => self::isWrittenDay($range->start, $text)),
            )),
            [], // the questions are the PHP's (ExtractionRules), asked after this review
            null === $extraction->listing ? null : self::checkedListing($extraction->listing, $text),
        );
    }

    /**
     * An end on the last day of a month means the whole month: "au 31 août", "fin août",
     * "juillet et août" all end on 1 September. Owners and the model write it both ways; the one
     * night of difference is not worth a wrong reading (evaluation of 24/09: 6 failures out of 30
     * on this day alone).
     */
    public static function monthEnd(\DateTimeImmutable $end): \DateTimeImmutable
    {
        return $end->format('j') === $end->format('t') ? $end->modify('+1 day') : $end;
    }

    public static function wholeMonth(ExtractedPeriod $period): ExtractedPeriod
    {
        return null === $period->end ? $period : new ExtractedPeriod(
            $period->label, $period->start, self::monthEnd($period->end), $period->prices, $period->minimumNights, $period->saturdayArrival,
        );
    }

    /**
     * The first day of a taken range must be written: "18/07", "18 juillet", or the month itself
     * for the 1st ("juillet complet"). The model sometimes makes one up for "pas de dispo
     * jusqu'au 22 août" or "libre à partir du 21 août" (24/09: from 1 January). Dropped then.
     */
    private static function isWrittenDay(\DateTimeImmutable $day, string $text): bool
    {
        $words = MonthLabel::fold($text);
        $d = (int) $day->format('j');
        $m = (int) $day->format('n');
        $names = implode('|', MonthLabel::namesOf($m));

        return 1 === preg_match(\sprintf('~(?<!\\d)0?%d\\s*[/.-]\\s*0?%d(?!\\d)~', $d, $m), $words)
            || 1 === preg_match(\sprintf('~(?<!\\d)0?%d(?:er)?\\s+(?:%s)\\b~', $d, $names), $words)
            || (1 === $d && 1 === preg_match(\sprintf('~\\b(?:%s)\\b~', $names), $words));
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
        $dated = static fn (ExtractedPeriod $period): bool => null !== $period->start || null !== $period->end;
        $prices = static function (ExtractedPeriod $period): string {
            $keys = array_map(static fn (ExtractedPrice $price): string => $price->key(), $period->prices);
            sort($keys);

            return implode(',', $keys);
        };
        // "Prix : 700 €" (no unit) repeats "Juin : 700 € / semaine": the same amounts. Not when the
        // undated one has its own unit ("hors saison 400 € la semaine" is a rate of its own).
        $unitless = static fn (ExtractedPeriod $period): bool => [] === array_filter($period->prices, static fn (ExtractedPrice $price): bool => PriceUnit::Unknown !== $price->unit);
        $amountsOf = static function (ExtractedPeriod $period): string {
            $amounts = array_map(static fn (ExtractedPrice $price): int => $price->amount, $period->prices);
            sort($amounts);

            return implode(',', $amounts);
        };
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
                if ($i !== $j && !$dated($period) && $dated($other)
                    && ($prices($period) === $prices($other) || ($unitless($period) && $amountsOf($period) === $amountsOf($other)))) {
                    continue 2;
                }
            }
            $kept[] = $period;
            $seen[$period->key()] = true;
        }

        return $kept;
    }

    /**
     * Words just after an amount that make it a conditional price, not a rate: "400 € si 2
     * semaines", "850 € à partir de 15 jours", "au-delà de 3 semaines". Not "à partir du 15
     * juin" (a date: the rate starts then) nor "pour 2 semaines" (a stay price).
     */
    private const string CONDITIONAL_AFTER = '/^ (?:(?:euros?|eur) )?(?:si \\d|a partir de \\d+ (?:jours?|nuits?|semaines?)|au dela d)/';

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
            $rest = substr($amounts, $offset + \strlen((string) $amount));
            $after = strtok($rest, ' ');
            if (1 === preg_match(self::FLOOR_OR_DISCOUNT, $before)
                || 1 === preg_match(self::CONDITIONAL_AFTER, $rest)
                || self::isOtherBound(strrchr(' '.$before, ' '), $amount, lower: true)
                || self::isOtherBound(false === $after ? '' : $after, $amount, lower: false)) {
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
    private static function isOtherBound(string|false $word, int $amount, bool $lower): bool
    {
        $word = trim((string) $word);
        if (1 !== preg_match('/^\d+$/', $word) || (int) $word < 50 || (int) $word >= 2000 || (int) $word === $amount) {
            return false;
        }

        // A range is written low to high ("560-840 €"). "900 € (850 € au-delà de 15 jours)" is
        // a rate followed by a cheaper one, not a range.
        return $lower ? (int) $word < $amount : (int) $word > $amount;
    }

    private static function onlyInPriceField(int $amount, string $amounts): bool
    {
        return 0 === preg_match('/(?<!prix )(?<= )'.$amount.'(?= )/', $amounts);
    }

    /**
     * A unit is believed when it is written next to the amount: in the four words after it
     * ("650 € la semaine", "375 €/sem"), or in the six before it without another price in between
     * ("la semaine : 650 €"), or in a heading that sets it for the whole list ("Tarifs à la
     * semaine :"). Written somewhere else in the text is not enough: in "800 € la semaine et du
     * 20 au 31 août (1200 €)", 1200 has no unit.
     */
    private static function checkedUnit(ExtractedPrice $price, string $words, string $amounts): ExtractedPrice
    {
        $pattern = self::UNIT_WORDS[$price->unit->value] ?? null;
        if (null === $pattern) {
            return $price;
        }

        $unknown = new ExtractedPrice($price->amount, PriceUnit::Unknown);
        if (1 !== preg_match($pattern, $words)) {
            return $unknown;
        }
        if (1 === preg_match(self::UNIT_HEADINGS[$price->unit->value] ?? '/(?!)/', $words)) {
            return $price;
        }

        preg_match_all('/(?<= )'.$price->amount.'(?= )/', $amounts, $found, \PREG_OFFSET_CAPTURE);
        if ([] === $found[0]) {
            return $price; // not found as such (glued to something): no local check possible
        }

        foreach ($found[0] as [, $offset]) {
            $after = \array_slice(explode(' ', trim(substr($amounts, $offset + \strlen((string) $price->amount)))), 0, 4);
            $before = [];
            foreach (array_reverse(\array_slice(explode(' ', trim(substr($amounts, 0, $offset))), -6)) as $word) {
                if (self::isOtherPrice($word)) {
                    break;
                }
                array_unshift($before, $word);
            }
            if (1 === preg_match($pattern, ' '.implode(' ', $after).' ') || 1 === preg_match($pattern, ' '.implode(' ', $before).' ')) {
                return $price;
            }
        }

        return $unknown;
    }

    /** A number that looks like another price (not a day, not a year): the unit before it is its own. */
    private static function isOtherPrice(string $word): bool
    {
        return 1 === preg_match('/^\d+$/', $word) && (int) $word >= 50 && !((int) $word >= 2000 && (int) $word <= 2100);
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

        // Written without any doubt but forgotten by the model: ticked (AmenityEvidence::STRONG).
        foreach (AmenityEvidence::STRONG as $amenity) {
            if (!\in_array($amenity, $amenities, true) && AmenityEvidence::isWritten($amenity, $text)) {
                $amenities[] = $amenity;
            }
        }

        if (\in_array(Amenity::CoveredTerrace, $amenities, true) && !\in_array(Amenity::Terrace, $amenities, true)) {
            $amenities[] = Amenity::Terrace;
        }

        return new ExtractedListing(
            $listing->type, self::writtenCapacity($listing->capacity, $text), $listing->bedrooms, self::writtenSurface($listing->surface, $text), $listing->district,
            $amenities, $listing->petsPolicy, $listing->otherFeatures, $listing->rejected,
        );
    }

    /**
     * The living area only: the number must be written with "m²" and not right after
     * "parcelle", "terrasse", "terrain"… ("parcelle privative de 100 m2" is not a surface).
     */
    private static function writtenSurface(?int $surface, string $text): ?int
    {
        if (null === $surface) {
            return null;
        }

        $words = self::amountWords($text);
        preg_match_all('/(?<= )'.$surface.' (?:m ?2|m²|metres? carres?)(?= )/u', $words, $found, \PREG_OFFSET_CAPTURE);

        foreach ($found[0] as [, $offset]) {
            $before = \array_slice(explode(' ', trim(substr($words, 0, $offset))), -5);
            if ([] === array_intersect($before, ['parcelle', 'parcelles', 'terrain', 'terrasse', 'terrasses', 'emplacement', 'jardin', 'deck', 'parking'])) {
                return $surface;
            }
        }

        return null;
    }

    /**
     * "6 personnes", "5 couchages": one number written, it is the capacity; none, it stays
     * empty; several, the model's pick if it is one of them. A range ("4-6 personnes") is none.
     */
    private static function writtenCapacity(?int $capacity, string $text): ?int
    {
        $words = self::amountWords($text);
        // On the folded text, not the amount words: "32 – 3 couchages" is 3, "4-6 personnes" a range.
        preg_match_all('/(?<![\d.,])(?<!\d-)(?<!\d -)(?<!\d a )(?<!\d à )(\d{1,2}) ?(?:personnes?|pers\b|couchages?|places\b|voyageurs)/', MonthLabel::fold($text), $found);
        // "Nombre de couchages : 6", "Capacité : 7" (the order of the form fields of listing sites).
        preg_match_all('/ (?:couchages?|capacite(?: d accueil)?) (\d{1,2})(?= )(?! (?:chambres?|lits?|m ?2|m²|adultes?|enfants?|pers\w*|bebes?))/', $words, $reversed);
        // The label first only when no number comes before a noun: "6 couchages (3 adultes)" is 6.
        $written = array_values(array_unique(array_map(intval(...), [] !== $found[1] ? $found[1] : $reversed[1])));

        return match (true) {
            1 === \count($written) => $written[0],
            \in_array($capacity, $written, true) => $capacity,
            default => null,
        };
    }
}
