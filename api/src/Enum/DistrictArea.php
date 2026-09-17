<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Where a district sits between the beach and the main road, as read on the official site plan.
 */
enum DistrictArea: string
{
    case Dunes = 'dunes';
    case Central = 'central';
    case Roadside = 'roadside';
}
