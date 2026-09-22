<?php

declare(strict_types=1);

namespace App\Enum;

enum AccommodationType: string
{
    case Bungalow = 'bungalow';
    case MobileHome = 'mobile_home';
    case Caravan = 'caravan';

    /** As the site writes it, in emails too. */
    public function label(): string
    {
        return match ($this) {
            self::Bungalow => 'Bungalow',
            self::MobileHome => 'Mobil-home',
            self::Caravan => 'Caravane',
        };
    }
}
