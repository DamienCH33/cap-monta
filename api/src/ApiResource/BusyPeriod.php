<?php

declare(strict_types=1);

namespace App\ApiResource;

/**
 * A taken period as the public sees it: no source, no note. See PublicCalendar.
 */
final readonly class BusyPeriod
{
    public function __construct(
        public \DateTimeImmutable $start,
        public \DateTimeImmutable $end,
    ) {
    }
}
