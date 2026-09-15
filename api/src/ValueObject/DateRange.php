<?php

declare(strict_types=1);

namespace App\ValueObject;

final readonly class DateRange
{
    public function __construct(
        public \DateTimeImmutable $start,
        public \DateTimeImmutable $end,
    ) {
    }

    public function overlaps(self $other): bool
    {
        return $this->start < $other->end && $this->end > $other->start;
    }

    public function contains(\DateTimeImmutable $day): bool
    {
        return $this->start <= $day && $day < $this->end;
    }

    public function nights(): int
    {
        return (int) $this->start->diff($this->end)->days;
    }
}
