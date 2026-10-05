<?php

declare(strict_types=1);

namespace App\I18n;

/**
 * Les langues du site (fr, en, nl, de). Le front envoie la langue de la page dans Accept-Language :
 * l'API répond dans cette langue (messages, emails). Le français reste la langue source.
 */
final class Locales
{
    public const string SOURCE = 'fr';
    public const array ALL = ['fr', 'en', 'nl', 'de'];

    public static function normalize(?string $locale): string
    {
        $locale = strtolower(substr(trim((string) $locale), 0, 2));

        return \in_array($locale, self::ALL, true) ? $locale : self::SOURCE;
    }

    /** Préfixe des adresses du site : '' en français, '/en' en anglais… */
    public static function pathPrefix(string $locale): string
    {
        $locale = self::normalize($locale);

        return self::SOURCE === $locale ? '' : '/'.$locale;
    }
}
