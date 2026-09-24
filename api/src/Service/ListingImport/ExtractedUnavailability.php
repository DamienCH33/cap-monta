<?php

declare(strict_types=1);

namespace App\Service\ListingImport;

/**
 * Dates the listing says are taken ("déjà occupé du 27/06/27 au 01/08/27"). Both bounds are
 * required: "libre à partir du 22 août" does not say since when it is taken.
 */
final readonly class ExtractedUnavailability
{
    public function __construct(
        public \DateTimeImmutable $start,
        public \DateTimeImmutable $end,
    ) {
        ExtractionDates::checkOrder($start, $end);
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $start = ExtractionDates::parse($data['start'] ?? null, 'start', false);
        $end = ExtractionDates::parse($data['end'] ?? null, 'end', false);
        \assert(null !== $start && null !== $end);

        return new self($start, $end);
    }

    /**
     * @return array{start: string, end: string}
     */
    public function toArray(): array
    {
        return ['start' => $this->start->format('Y-m-d'), 'end' => $this->end->format('Y-m-d')];
    }

    /**
     * The same days, whatever the cutting: ranges that overlap or touch become one ("déjà loué"
     * week after week gives a single stretch). The calendar stores them this way anyway.
     *
     * @param list<self> $ranges
     *
     * @return list<self>
     */
    public static function merged(array $ranges): array
    {
        usort($ranges, static fn (self $a, self $b): int => $a->start <=> $b->start);
        $merged = [];

        foreach ($ranges as $range) {
            $last = array_key_last($merged);
            if (null !== $last && $range->start <= $merged[$last]->end) {
                $merged[$last] = new self($merged[$last]->start, max($merged[$last]->end, $range->end));
            } else {
                $merged[] = $range;
            }
        }

        return $merged;
    }

    public function key(): string
    {
        return $this->start->format('Y-m-d').'→'.$this->end->format('Y-m-d');
    }
}
