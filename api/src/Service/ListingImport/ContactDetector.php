<?php

declare(strict_types=1);

namespace App\Service\ListingImport;

/**
 * Finds phone numbers and email addresses in a listing text. No AI here: a regular expression
 * does it better, for free, every time. The phone stays private on Cap Monta until a request is
 * accepted, so a number left in a public description would defeat that rule.
 */
final class ContactDetector
{
    private const string PHONE = '/(?<![\d+])(?:\+33[\s.\-]?|0)[1-9](?:[\s.\-]?\d{2}){4}(?!\d)/';
    private const string EMAIL = '/[\p{L}0-9._%+\-]+@[\p{L}0-9.\-]+\.[a-z]{2,}/iu';

    /**
     * The text with every phone number and email replaced by a neutral marker: what is sent to
     * the AI provider. Extracting rates never needs them, and the free plan may keep the text.
     */
    public function mask(string $text): string
    {
        return (string) preg_replace([self::PHONE, self::EMAIL], ['[téléphone]', '[email]'], $text);
    }

    /**
     * @return list<string> what was found, as written
     */
    public function find(string $text): array
    {
        preg_match_all(self::PHONE, $text, $phones);
        preg_match_all(self::EMAIL, $text, $emails);

        return array_values(array_unique([...$phones[0], ...$emails[0]]));
    }
}
