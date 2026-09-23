<?php

declare(strict_types=1);

namespace App\Service\ListingImport;

/**
 * What the import agent reads in a pasted listing: rate periods, taken dates, the accommodation
 * form (optional) and the questions it could not settle alone. The same shape is used for the expected answers of the evaluation
 * set, so both go through the same checks.
 */
final readonly class ListingExtraction
{
    /**
     * @param list<ExtractedPeriod>         $periods
     * @param list<ExtractedUnavailability> $unavailable
     * @param list<string>                  $questions
     */
    public function __construct(
        public array $periods,
        public array $unavailable,
        public array $questions = [],
        public ?ExtractedListing $listing = null,
    ) {
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            array_map(
                static fn (array $period): ExtractedPeriod => ExtractedPeriod::fromArray($period),
                self::listOfObjects($data, 'periods'),
            ),
            array_map(
                static fn (array $range): ExtractedUnavailability => ExtractedUnavailability::fromArray($range),
                self::listOfObjects($data, 'unavailable'),
            ),
            array_values(array_filter(
                \is_array($data['questions'] ?? null) ? $data['questions'] : [],
                static fn (mixed $question): bool => \is_string($question) && '' !== trim($question),
            )),
            \is_array($data['listing'] ?? null) ? ExtractedListing::fromArray($data['listing']) : null,
        );
    }

    public function withListing(?ExtractedListing $listing): self
    {
        return new self($this->periods, $this->unavailable, $this->questions, $listing);
    }

    /**
     * @param list<string> $questions added to the model's own, without duplicates
     */
    public function withQuestions(array $questions): self
    {
        return new self(
            $this->periods,
            $this->unavailable,
            array_values(array_unique([...$this->questions, ...$questions])),
            $this->listing,
        );
    }

    /**
     * @return array<string, mixed> the same shape fromArray() reads
     */
    public function toArray(): array
    {
        return [
            'periods' => array_map(static fn (ExtractedPeriod $period): array => $period->toArray(), $this->periods),
            'unavailable' => array_map(static fn (ExtractedUnavailability $range): array => $range->toArray(), $this->unavailable),
            'questions' => $this->questions,
            'listing' => $this->listing?->toArray(),
        ];
    }

    /**
     * Every amount the extraction mentions, to check each one against the source text.
     *
     * @return list<int>
     */
    public function amounts(): array
    {
        $amounts = [];

        foreach ($this->periods as $period) {
            foreach ($period->prices as $price) {
                $amounts[] = $price->amount;
            }
        }

        return $amounts;
    }

    /**
     * @param array<mixed> $data
     *
     * @return list<array<mixed>>
     */
    private static function listOfObjects(array $data, string $key): array
    {
        $items = $data[$key] ?? null;

        if (!\is_array($items) || !array_is_list($items)) {
            throw new InvalidExtractionException(\sprintf('« %s » doit être une liste (éventuellement vide).', $key));
        }

        foreach ($items as $item) {
            if (!\is_array($item)) {
                throw new InvalidExtractionException(\sprintf('Chaque élément de « %s » est un objet.', $key));
            }
        }

        /* @var list<array<mixed>> $items */
        return $items;
    }
}
