<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * What a price read in a listing pays for. "Stay" is the whole period at once ("1200 € pour
 * 2 semaines"); "unknown" is a price written without a unit ("septembre : 400 €"), which the
 * owner must settle: guessing "per week" would be inventing a rate.
 */
enum PriceUnit: string
{
    case Week = 'week';
    case Night = 'night';
    case Stay = 'stay';
    case Unknown = 'unknown';
}
