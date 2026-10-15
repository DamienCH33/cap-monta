<?php

declare(strict_types=1);

namespace App\Service\Mail;

use App\I18n\Locales;

/**
 * Dates and prices as an email writes them, in the recipient's language. The French formats are
 * the ones the emails always had: "samedi 3 juillet 2027", "jeudi 24 septembre à 14h00", "1 234 €".
 */
final class MailFormat
{
    /** ICU locale and patterns by language: date, then date and time (Paris time). */
    private const array FORMATS = [
        'fr' => ['fr_FR', 'EEEE d MMMM y', "EEEE d MMMM 'à' HH'h'mm"],
        'en' => ['en_GB', 'EEEE d MMMM y', "EEEE d MMMM 'at' HH:mm"],
        'nl' => ['nl_NL', 'EEEE d MMMM y', "EEEE d MMMM 'om' HH:mm"],
        'de' => ['de_DE', 'EEEE, d. MMMM y', "EEEE, d. MMMM 'um' HH:mm 'Uhr'"],
    ];

    /** "samedi 3 juillet 2027", "Saturday 3 July 2027", "zaterdag 3 juli 2027", "Samstag, 3. Juli 2027". */
    public static function date(\DateTimeImmutable $date, string $locale): string
    {
        [$icu, $pattern] = self::FORMATS[Locales::normalize($locale)];

        return (string) (new \IntlDateFormatter($icu, \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, null, null, $pattern))->format($date);
    }

    /** The answer deadline, in Paris time: "jeudi 24 septembre à 14h00", "Thursday 24 September at 14:00"… */
    public static function dateTime(\DateTimeImmutable $date, string $locale): string
    {
        [$icu, , $pattern] = self::FORMATS[Locales::normalize($locale)];

        return (string) (new \IntlDateFormatter($icu, \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, 'Europe/Paris', null, $pattern))->format($date);
    }

    /** Whole euros: "1 234 €", "€1,234", "€ 1.234", "1.234 €". */
    public static function euros(int $cents, string $locale): string
    {
        return match (Locales::normalize($locale)) {
            'en' => '€'.number_format($cents / 100, 0, '.', ','),
            'nl' => '€ '.number_format($cents / 100, 0, ',', '.'),
            'de' => number_format($cents / 100, 0, ',', '.').' €',
            default => number_format($cents / 100, 0, ',', ' ').' €',
        };
    }
}
