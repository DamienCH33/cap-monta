<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;

/**
 * A taken period as the public sees it: no source, no note. See PublicCalendar.
 */
final readonly class BusyPeriod
{
    public function __construct(
        #[Context(normalizationContext: [DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
        public \DateTimeImmutable $start,
        #[Context(normalizationContext: [DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
        public \DateTimeImmutable $end,
    ) {
    }
}
