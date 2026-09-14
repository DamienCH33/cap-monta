<?php

declare(strict_types=1);

namespace App\Enum;

enum AccommodationType: string
{
    case Bungalow = 'bungalow';
    case MobileHome = 'mobile_home';
    case Caravan = 'caravan';
}
