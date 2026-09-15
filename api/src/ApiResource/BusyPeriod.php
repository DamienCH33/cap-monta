<?php

declare(strict_types=1);

namespace App\ApiResource;

final readonly class BusyPeriod
{
    public function __construct(
        public \DateTimeImmutable $start,
        public \DateTimeImmutable $end,
        public string $source,
    ) {
    }
}
