<?php

declare(strict_types=1);

namespace App\Service\ListingImport\Evaluation;

/**
 * Every number written in a text, thousands separators included ("1.000", "2 950"). An amount
 * the extraction returns that is not among them was not read: it was made up.
 */
final class SourceAmounts
{
    /**
     * @return list<int>
     */
    public static function in(string $text): array
    {
        // A space or a dot followed by exactly three digits is a thousands separator.
        preg_match_all('/\d{1,3}(?:[ .\x{202F}\x{00A0}]\d{3})+(?!\d)|\d+/u', $text, $matches);

        return array_values(array_unique(array_map(
            static fn (string $number): int => (int) preg_replace('/\D/u', '', $number),
            $matches[0],
        )));
    }

    /**
     * @param list<int> $amounts
     *
     * @return list<int> the amounts absent from the text
     */
    public static function invented(array $amounts, string $text): array
    {
        return array_values(array_diff($amounts, self::in($text)));
    }
}
