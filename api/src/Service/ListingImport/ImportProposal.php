<?php

declare(strict_types=1);

namespace App\Service\ListingImport;

use App\Enum\PriceUnit;
use App\Service\Pricing\RateRules;

/**
 * What the owner sees on the "check what we read" screen (lot 4c): the extraction turned into
 * rows the rates and calendar screens understand — cents, both bounds, one weekly and one nightly
 * price — with a flag wherever the owner has something to decide.
 *
 * Nothing is guessed here either: a price without a unit is proposed as weekly (the CHM habit)
 * but flagged, a stay price is converted only when its dates are known, a row without dates or
 * already past is proposed unticked. The owner validates, the API checks again (ImportApplier).
 */
final class ImportProposal
{
    /** The calendar goes 18 months ahead (OwnerCalendarController). */
    public const string CALENDAR_HORIZON = '+18 months';

    /**
     * @param array<string, array{resort: string, slug: string}> $districts known districts by name
     * @param list<string>                                       $contacts  phones and emails found in the text
     *
     * @return array<string, mixed>
     */
    public static function build(ListingExtraction $extraction, array $contacts, string $text, array $districts, \DateTimeImmutable $today): array
    {
        return [
            'listing' => self::listing($extraction->listing ?? new ExtractedListing(), $contacts, $text, $districts),
            'periods' => array_map(static fn (ExtractedPeriod $period): array => self::period($period, $today), $extraction->periods),
            'unavailable' => self::unavailable($extraction->unavailable, $today),
            'questions' => $extraction->questions,
            'contacts' => $contacts,
        ];
    }

    /**
     * @param list<string>                                       $contacts
     * @param array<string, array{resort: string, slug: string}> $districts
     *
     * @return array<string, mixed>
     */
    private static function listing(ExtractedListing $listing, array $contacts, string $text, array $districts): array
    {
        $district = null === $listing->district ? null : ($districts[$listing->district] ?? null);

        return [
            'resort' => $district['resort'] ?? self::resortIn($text),
            'type' => $listing->type?->value,
            'capacity' => $listing->capacity,
            'bedrooms' => $listing->bedrooms,
            'surface' => $listing->surface,
            'district' => $district['slug'] ?? null,
            'amenities' => array_map(static fn ($amenity): string => $amenity->value, $listing->amenities),
            'petsPolicy' => $listing->petsPolicy?->value,
            'otherFeatures' => self::otherFeatures($listing),
            'description' => self::description($text, $contacts),
        ];
    }

    /**
     * The domain named in the text, when the district did not say it: Euronat, or the CHM
     * (Montalivet, Hélio-Marin). Both named, or neither: the owner chooses.
     */
    private static function resortIn(string $text): ?string
    {
        $words = AmenityEvidence::words($text);
        $euronat = str_contains($words, ' euronat ');
        $chm = 1 === preg_match('/ (?:chm|montalivet|helio ?marin) /', $words);

        return match (true) {
            $euronat && !$chm => 'euronat',
            $chm && !$euronat => 'chm',
            default => null,
        };
    }

    /**
     * What the owner is shown as "also found": not an equipment he already sees ticked ("machine
     * à laver" is the washing machine box), short entries only.
     *
     * @return list<string>
     */
    private static function otherFeatures(ExtractedListing $listing): array
    {
        return array_values(array_filter($listing->otherFeatures, static function (string $feature) use ($listing): bool {
            foreach ($listing->amenities as $amenity) {
                if (AmenityEvidence::isWritten($amenity, $feature) && str_word_count(MonthLabel::fold($feature)) <= 4) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * The owner's own words as the description, without his phone and email: on Cap Monta they
     * are given to the traveller only once a request is accepted (ADR 013).
     *
     * @param list<string> $contacts
     */
    public static function description(string $text, array $contacts): string
    {
        $text = str_replace($contacts, '', $text);
        $text = (string) preg_replace('/[ \t]{2,}/', ' ', $text);
        $text = (string) preg_replace('/\n{3,}/', "\n\n", $text);

        return mb_substr(trim($text), 0, 5000);
    }

    /**
     * @return array<string, mixed>
     */
    private static function period(ExtractedPeriod $period, \DateTimeImmutable $today): array
    {
        $weekly = $nightly = $stay = null;
        $unitToConfirm = false;
        $nights = null === $period->start || null === $period->end ? null : (int) $period->start->diff($period->end)->days;

        $unknown = null;
        foreach ($period->prices as $price) {
            $cents = $price->amount * 100;
            if (PriceUnit::Week === $price->unit) {
                $weekly ??= $cents;
            } elseif (PriceUnit::Night === $price->unit) {
                $nightly ??= $cents;
            } elseif (PriceUnit::Stay === $price->unit) {
                $stay ??= $cents;
            } else {
                $unknown ??= $cents;
            }
        }

        // No unit written: proposed as the week, the CHM habit, and flagged for the owner.
        if (null !== $unknown && null === $weekly) {
            $weekly = $unknown;
            $unitToConfirm = true;
        } elseif (null !== $unknown && null === $nightly) {
            $nightly = $unknown;
            $unitToConfirm = true;
        }

        // "1200 € pour 2 semaines": the price of the whole stay, spread over its nights. Rounded to
        // the euro, like the rates screen shows it.
        if (null !== $stay && null !== $nights && $nights > 0) {
            if ($nights >= 7 && null === $weekly) {
                $weekly = (int) round($stay * 7 / $nights / 100) * 100;
            } elseif ($nights < 7 && null === $nightly) {
                $nightly = (int) round($stay / $nights / 100) * 100;
            }
        }

        $datesMissing = null === $period->start || null === $period->end;
        $past = null !== $period->end && $period->end <= $today;
        $tooFar = null !== $period->end && $period->end > $today->modify(RateRules::HORIZON);
        $priced = null !== $weekly || null !== $nightly;

        return [
            'label' => $period->label,
            'start' => $period->start?->format('Y-m-d'),
            'end' => $period->end?->format('Y-m-d'),
            'weeklyPrice' => $weekly,
            'nightlyPrice' => $nightly,
            'minimumNights' => $period->minimumNights ?? 1,
            'saturdayArrival' => $period->saturdayArrival,
            'stayPrice' => $stay,
            'unitToConfirm' => $unitToConfirm,
            'datesMissing' => $datesMissing,
            'past' => $past,
            'tooFar' => $tooFar,
            'selected' => !$datesMissing && !$past && !$tooFar && $priced,
        ];
    }

    /**
     * Merged, cut at today, flagged beyond the calendar.
     *
     * @param list<ExtractedUnavailability> $ranges
     *
     * @return list<array{start: string, end: string, tooFar: bool, selected: bool}>
     */
    private static function unavailable(array $ranges, \DateTimeImmutable $today): array
    {
        $rows = [];

        foreach (ExtractedUnavailability::merged($ranges) as $range) {
            if ($range->end <= $today) {
                continue;
            }
            $start = max($range->start, $today);
            $tooFar = $range->end > $today->modify(self::CALENDAR_HORIZON);
            $rows[] = ['start' => $start->format('Y-m-d'), 'end' => $range->end->format('Y-m-d'), 'tooFar' => $tooFar, 'selected' => !$tooFar];
        }

        return $rows;
    }
}
