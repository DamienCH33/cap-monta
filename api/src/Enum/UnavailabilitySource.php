<?php

declare(strict_types=1);

namespace App\Enum;

enum UnavailabilitySource: string
{
    case Booking = 'booking';
    case Block = 'block';
    case Import = 'import';
    case Ical = 'ical';
}
