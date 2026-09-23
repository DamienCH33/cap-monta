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

    public function key(): string
    {
        return $this->start->format('Y-m-d').'→'.$this->end->format('Y-m-d');
    }
}
