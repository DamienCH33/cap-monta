<?php

declare(strict_types=1);

namespace App\Service\ListingImport;

use App\Enum\PriceUnit;

/**
 * A price as written in the listing, in whole euros. Never computed: "1200 € pour 2 semaines"
 * stays 1200 per stay; turning it into a weekly rate is the application's job, not the model's.
 */
final readonly class ExtractedPrice
{
    public function __construct(
        public int $amount,
        public PriceUnit $unit,
    ) {
        if ($amount <= 0) {
            throw new InvalidExtractionException(\sprintf('Un prix doit être positif (%d).', $amount));
        }
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $amount = $data['amount'] ?? null;
        $unit = \is_string($data['unit'] ?? null) ? PriceUnit::tryFrom($data['unit']) : null;

        if (!\is_int($amount) || null === $unit) {
            throw new InvalidExtractionException('Un prix attend « amount » (entier) et « unit » (week, night, stay, unknown).');
        }

        return new self($amount, $unit);
    }

    public function key(): string
    {
        return $this->amount.'/'.$this->unit->value;
    }
}
