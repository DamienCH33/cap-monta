<?php

declare(strict_types=1);

namespace App\Service\ListingImport;

use App\Enum\PriceUnit;
use App\ValueObject\DateRange;

/**
 * The checks that need no AI. Whatever the model answered, these situations always end with a
 * question to the owner: a date is missing, a unit is missing, there is no rate at all, or two
 * periods overlap (the database would refuse them anyway, ADR 003).
 */
final class ExtractionRules
{
    /**
     * Questions that do not depend on the model's judgement: if one of these holds, the owner
     * must be asked, full stop.
     *
     * @return list<string>
     */
    public static function questions(ListingExtraction $extraction, ?\DateTimeImmutable $publishedAt = null): array
    {
        $questions = [];
        $periods = $extraction->periods;

        // Rates of a season already over when the listing was published: a year left unchanged.
        $past = null === $publishedAt ? [] : array_filter(
            $periods,
            static fn (ExtractedPeriod $period): bool => null !== $period->end && $period->end <= $publishedAt,
        );
        if ([] !== $past) {
            $questions[] = \sprintf(
                'Votre annonce donne des tarifs de %s, déjà passés : quels sont vos tarifs actuels ?',
                implode(', ', array_unique(array_map(static fn (ExtractedPeriod $period): string => (string) ($period->start ?? $period->end)?->format('Y'), $past))),
            );
        }

        if ([] === $periods) {
            $questions[] = 'Aucun tarif n\'a été trouvé dans votre annonce : indiquez vos prix et leurs dates.';
        }

        foreach ($periods as $period) {
            if (null === $period->start || null === $period->end) {
                $questions[] = \sprintf('À quelles dates exactes correspond « %s » ?', $period->label);
            }
            if ([] === $period->prices) {
                $questions[] = \sprintf('Quel est le prix pour « %s » ?', $period->label);
            }
            foreach ($period->prices as $price) {
                if (PriceUnit::Unknown === $price->unit) {
                    $questions[] = \sprintf('%d € pour « %s » : est-ce le prix de la semaine, de la nuit ou du séjour ?', $price->amount, $period->label);
                }
            }
        }

        foreach ($periods as $i => $a) {
            foreach (\array_slice($periods, $i + 1) as $b) {
                if (null === $a->start || null === $a->end || null === $b->start || null === $b->end) {
                    continue;
                }
                if (new DateRange($a->start, $a->end)->overlaps(new DateRange($b->start, $b->end))) {
                    $questions[] = \sprintf('« %s » et « %s » se chevauchent : une des deux est-elle déjà louée ?', $a->label, $b->label);
                }
            }
        }

        return $questions;
    }
}
