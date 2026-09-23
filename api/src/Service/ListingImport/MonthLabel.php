<?php

declare(strict_types=1);

namespace App\Service\ListingImport;

/**
 * Turns a period label made only of months ("septembre", "juillet et août", "juin à
 * septembre 2027", "avril / mai") into exact dates, without the model. Computing dates is not
 * a job for a language model: it was the first source of errors in the evaluation (23/09).
 *
 * Anything else in the label ("mi-juin", "hors saison", "du 4 au 11 juillet", a date in
 * brackets) and the label is left alone: null is returned.
 */
final class MonthLabel
{
    private const array MONTHS = [
        'janvier' => 1, 'fevrier' => 2, 'mars' => 3, 'avril' => 4, 'mai' => 5, 'juin' => 6,
        'juillet' => 7, 'aout' => 8, 'septembre' => 9, 'octobre' => 10, 'novembre' => 11, 'decembre' => 12,
    ];

    private const array NAMES = [
        1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril', 5 => 'mai', 6 => 'juin',
        7 => 'juillet', 8 => 'août', 9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre',
    ];

    /** Words that change nothing to the dates ("haute saison : juillet et août", "tout le mois de mai"). */
    private const array NOISE = [
        'haute', 'basse', 'moyenne', 'saison', 'tarif', 'tarifs', 'prix', 'mois', 'tout', 'de', 'd', 'en',
        'le', 'la', 'les', 'l', 'et', 'par', 'semaine', 'nuit', 'sem',
    ];

    /**
     * @param int $defaultYear used when the label does not give one (the publication year)
     *
     * @return list<array{start: \DateTimeImmutable, end: \DateTimeImmutable, label: string}>|null
     *                                                                                             one entry per run of consecutive months ("juin et septembre" gives two); null when the
     *                                                                                             label is not only months
     */
    public static function periods(string $label, int $defaultYear): ?array
    {
        $text = self::fold($label);

        // "septembre (disponible à partir du 29 août)": the period starts on that earlier day.
        $earlyStart = null;
        $monthNames = implode('|', array_keys(self::MONTHS));
        if (1 === preg_match('/\(?\s*(?:dispo\w*\s+)?(?:a partir du|des le|du)\s+(\d{1,2})(?:er)?\s+('.$monthNames.')\s*\)?/', $text, $early)) {
            $earlyStart = [(int) $early[1], self::MONTHS[$early[2]]];
            $text = str_replace($early[0], ' ', $text);
        }

        // A bracket may carry what matters ("septembre (dispo dès le 29 août)"): hands off.
        if (preg_match_all('/\(([^)]*)\)/', $text, $brackets) > 0) {
            foreach ($brackets[1] as $inside) {
                if (1 === preg_match('/\d/', $inside)) {
                    return null;
                }
            }
            $text = (string) preg_replace('/\([^)]*\)/', ' ', $text);
        }

        // A price copied into the label ("avril / mai 400 € par semaine").
        $text = (string) preg_replace('/\d[\d .,]*\s*(?:€|eur\b|euros?\b)/u', ' ', $text);

        preg_match_all('/[a-z]+|\d+/', $text, $matches);
        $months = [];
        $ranges = [];
        $year = null;

        foreach ($matches[0] as $token) {
            if (isset(self::MONTHS[$token])) {
                $months[] = self::MONTHS[$token];
            } elseif (\in_array($token, ['a', 'au'], true) && [] !== $months) {
                $ranges[] = \count($months) - 1; // the next month closes a range opened by this one
            } elseif (1 === preg_match('/^20\d\d$/', $token) && null === $year) {
                $year = (int) $token;
            } elseif (1 === preg_match('/^\d\d$/', $token) && null === $year && [] !== $months) {
                $year = 2000 + (int) $token; // "septembre 26": a day comes before the month in French
            } elseif (!\in_array($token, self::NOISE, true)) {
                return null;
            }
        }

        if ([] === $months) {
            return null;
        }

        // Absolute month numbers, in the order written; a month smaller than the previous one
        // belongs to the next year ("novembre à février").
        $year ??= $defaultYear;
        $absolute = [];
        foreach ($months as $i => $month) {
            $value = $year * 12 + $month - 1;
            if ([] !== $absolute && $value < end($absolute)) {
                $value += 12;
                ++$year;
            }
            if (\in_array($i - 1, $ranges, true)) {
                for ($between = end($absolute) + 1; $between < $value; ++$between) {
                    $absolute[] = $between;
                }
            }
            $absolute[] = $value;
        }
        $absolute = array_values(array_unique($absolute));
        sort($absolute);

        $runs = [];
        foreach ($absolute as $value) {
            $last = array_key_last($runs);
            if (null !== $last && end($runs[$last]) === $value - 1) {
                $runs[$last][] = $value;
            } else {
                $runs[] = [$value];
            }
        }

        $periods = array_map(static fn (array $run): array => [
            'start' => self::firstDay($run[0]),
            'end' => self::firstDay(end($run) + 1),
            'label' => implode(' et ', array_map(static fn (int $value): string => self::NAMES[$value % 12 + 1], $run)),
        ], $runs);

        if (null !== $earlyStart) {
            [$day, $month] = $earlyStart;
            $first = $periods[0]['start'];
            $year = (int) $first->format('Y') - ($month > (int) $first->format('n') ? 1 : 0);
            $start = \DateTimeImmutable::createFromFormat('!Y-n-j', \sprintf('%d-%d-%d', $year, $month, $day));
            // Only an earlier day, a few weeks before at most; anything else is not understood.
            if (1 !== \count($periods) || false === $start || $start >= $first || $start < $first->modify('-31 days')) {
                return null;
            }
            $periods[0]['start'] = $start;
        }

        return $periods;
    }

    public static function fold(string $text): string
    {
        return mb_strtolower((string) transliterator_transliterate('Any-Latin; Latin-ASCII', $text));
    }

    private static function firstDay(int $absoluteMonth): \DateTimeImmutable
    {
        return new \DateTimeImmutable(\sprintf('%04d-%02d-01', intdiv($absoluteMonth, 12), $absoluteMonth % 12 + 1));
    }
}
