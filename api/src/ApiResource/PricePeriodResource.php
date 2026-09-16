<?php

declare(strict_types=1);

namespace App\ApiResource;

use App\Entity\PricePeriod;

final class PricePeriodResource
{
    public function __construct(
        public \DateTimeImmutable $startDate,
        public \DateTimeImmutable $endDate,
        public ?int $weeklyPrice,
        public ?int $nightlyPrice,
        public int $minimumNights,
    ) {
    }

    public static function fromEntity(PricePeriod $period): self
    {
        return new self(
            $period->getStartDate(),
            $period->getEndDate(),
            $period->getWeeklyPrice(),
            $period->getNightlyPrice(),
            $period->getMinimumNights(),
        );
    }
}
