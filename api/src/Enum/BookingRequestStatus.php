<?php

declare(strict_types=1);

namespace App\Enum;

enum BookingRequestStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}
