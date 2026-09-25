<?php

declare(strict_types=1);

namespace App\ApiResource;

use App\Entity\PricePeriod;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;

final class PricePeriodResource
{
    public function __construct(
        #[Context(normalizationContext: [DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
        public \DateTimeImmutable $startDate,
        #[Context(normalizationContext: [DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
        public \DateTimeImmutable $endDate,
        public ?int $weeklyPrice,
        public ?int $nightlyPrice,
        public int $minimumNights,
        public bool $saturdayArrival = false,
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
            $period->prefersSaturdayArrival(),
        );
    }
}
