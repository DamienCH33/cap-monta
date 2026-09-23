<?php

declare(strict_types=1);

namespace App\Service\ListingImport\Evaluation;

use App\Enum\Amenity;
use App\Service\ListingImport\ExtractedListing;
use App\Service\ListingImport\ExtractedPeriod;
use App\Service\ListingImport\ExtractedUnavailability;
use App\Service\ListingImport\ListingExtraction;

/**
 * Compares an extraction with the expected answer, strictly: a period counts only if its dates,
 * prices, minimum stay and Saturday rule all match. Close is wrong — an owner would publish it.
 */
final class ExtractionScorer
{
    public function score(EvalCase $case, ListingExtraction $actual): CaseScore
    {
        $expectedPeriods = $this->periodKeys($case->expected->periods);
        $actualPeriods = $this->periodKeys($actual->periods);
        $expectedRanges = $this->rangeKeys($case->expected->unavailable);
        $actualRanges = $this->rangeKeys($actual->unavailable);

        $listing = null === $case->expected->listing
            ? null
            : $this->compareListing($case->expected->listing, $actual->listing ?? new ExtractedListing(), $case->optionalAmenities);

        return new CaseScore(
            $case->id,
            \count($expectedPeriods),
            $this->difference($expectedPeriods, $actualPeriods),
            $this->difference($actualPeriods, $expectedPeriods),
            $this->difference($expectedRanges, $actualRanges),
            $this->difference($actualRanges, $expectedRanges),
            SourceAmounts::invented($actual->amounts(), $case->text),
            $case->needsClarification,
            [] !== $actual->questions,
            $listing['wrongFields'] ?? [],
            $listing['inventedAmenities'] ?? [],
            $listing['missedAmenities'] ?? [],
            $listing['lostFeatures'] ?? [],
            $actual->listing->rejected ?? [],
        );
    }

    /**
     * @param list<Amenity> $optional
     *
     * @return array{wrongFields: list<string>, inventedAmenities: list<string>, missedAmenities: list<string>, lostFeatures: list<string>}
     */
    private function compareListing(ExtractedListing $expected, ExtractedListing $actual, array $optional): array
    {
        $fields = [
            'type' => [$expected->type?->value, $actual->type?->value],
            'capacité' => [$expected->capacity, $actual->capacity],
            'chambres' => [$expected->bedrooms, $actual->bedrooms],
            'surface' => [$expected->surface, $actual->surface],
            'quartier' => [$this->normalize($expected->district), $this->normalize($actual->district)],
            'animaux' => [$expected->petsPolicy?->value, $actual->petsPolicy?->value],
        ];

        $wrongFields = [];
        foreach ($fields as $name => [$want, $got]) {
            if ($want !== $got) {
                $wrongFields[] = \sprintf('%s : attendu %s, trouvé %s', $name, $this->show($want), $this->show($got));
            }
        }

        $keys = static fn (array $amenities): array => array_map(static fn (Amenity $a): string => $a->value, $amenities);
        $wanted = $keys($expected->amenities);
        $got = $keys($actual->amenities);
        $tolerated = $keys($optional);

        return [
            'wrongFields' => $wrongFields,
            'inventedAmenities' => array_values(array_diff($got, $wanted, $tolerated)),
            'missedAmenities' => array_values(array_diff($wanted, $got)),
            'lostFeatures' => array_values(array_filter(
                $expected->otherFeatures,
                fn (string $feature): bool => !$this->reported($feature, $actual->otherFeatures),
            )),
        ];
    }

    /**
     * An out-of-list item is reported if one of the returned items contains it, or the reverse,
     * ignoring case, accents and punctuation: "hamac" matches "un hamac sur la terrasse".
     *
     * @param list<string> $reported
     */
    private function reported(string $feature, array $reported): bool
    {
        $wanted = (string) $this->normalize($feature);

        foreach ($reported as $item) {
            $item = (string) $this->normalize($item);
            if (str_contains($item, $wanted) || str_contains($wanted, $item)) {
                return true;
            }
        }

        return false;
    }

    private function normalize(?string $text): ?string
    {
        if (null === $text) {
            return null;
        }

        $ascii = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text);

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', false === $ascii ? mb_strtolower($text) : $ascii));
    }

    private function show(string|int|null $value): string
    {
        return null === $value ? 'rien' : (string) $value;
    }

    /**
     * @param list<ExtractedPeriod> $periods
     *
     * @return list<string>
     */
    private function periodKeys(array $periods): array
    {
        return array_map(static fn (ExtractedPeriod $period): string => $period->key(), $periods);
    }

    /**
     * @param list<ExtractedUnavailability> $ranges
     *
     * @return list<string>
     */
    private function rangeKeys(array $ranges): array
    {
        return array_map(static fn (ExtractedUnavailability $range): string => $range->key(), $ranges);
    }

    /**
     * Multiset difference: a period returned twice counts twice.
     *
     * @param list<string> $from
     * @param list<string> $remove
     *
     * @return list<string>
     */
    private function difference(array $from, array $remove): array
    {
        foreach ($remove as $key) {
            $index = array_search($key, $from, true);

            if (false !== $index) {
                unset($from[$index]);
            }
        }

        return array_values($from);
    }
}
