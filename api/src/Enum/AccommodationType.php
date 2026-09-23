<?php

declare(strict_types=1);

namespace App\Enum;

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

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
