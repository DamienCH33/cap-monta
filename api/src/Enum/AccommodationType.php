<?php

declare(strict_types=1);

namespace App\Enum;

use App\I18n\Locales;

/**
 * What a private owner rents out. Tents, "insolite" lodgings and camping pitches are left out on
 * purpose: at the CHM and at Euronat they belong to the operator, not to owners (ADR 026).
 * Studios and chalets are what owners rent at Euronat.
 */
enum AccommodationType: string
{
    case Bungalow = 'bungalow';
    case MobileHome = 'mobile_home';
    case Caravan = 'caravan';
    case Chalet = 'chalet';
    case Studio = 'studio';

    /** As the site writes it, in emails too. */
    public function label(): string
    {
        return match ($this) {
            self::Bungalow => 'Bungalow',
            self::MobileHome => 'Mobil-home',
            self::Caravan => 'Caravane',
            self::Chalet => 'Chalet',
            self::Studio => 'Studio',
        };
    }

    /** The label in one of the site's languages (the same words as the front's catalogues). */
    public function labelIn(string $locale): string
    {
        return match (Locales::normalize($locale)) {
            'en' => match ($this) {
                self::MobileHome => 'Mobile home',
                self::Caravan => 'Caravan',
                default => $this->label(),
            },
            'nl' => match ($this) {
                self::MobileHome => 'Stacaravan',
                self::Caravan => 'Caravan',
                default => $this->label(),
            },
            'de' => match ($this) {
                self::MobileHome => 'Mobilheim',
                self::Caravan => 'Wohnwagen',
                default => $this->label(),
            },
            default => $this->label(),
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
