<?php

declare(strict_types=1);

namespace App\Enum;

enum BookingRefusalReason: string
{
    case Unavailable = 'unavailable';
    case TooManyGuests = 'too_many_guests';
    case StayTooShort = 'stay_too_short';
}
