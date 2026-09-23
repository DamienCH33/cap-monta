<?php

declare(strict_types=1);

namespace App\Service\ListingImport;

/**
 * A rate period read in a listing. Dates stay null when the text does not give them
 * ("hors saison", "mi-juin"): an empty field the owner fills beats a guessed one.
 */
final readonly class ExtractedPeriod
{
    /**
     * @param list<ExtractedPrice> $prices
     */
    public function __construct(
        public string $label,
        public ?\DateTimeImmutable $start,
        public ?\DateTimeImmutable $end,
        public array $prices,
        public ?int $minimumNights = null,
        public bool $saturdayArrival = false,
    ) {
        ExtractionDates::checkOrder($start, $end);

        if (null !== $minimumNights && $minimumNights < 1) {
            throw new InvalidExtractionException('Le minimum de nuits doit être d\'au moins 1.');
        }
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $prices = $data['prices'] ?? null;
        $minimumNights = $data['minimumNights'] ?? null;

        if (!\is_array($prices) || !array_is_list($prices)) {
            throw new InvalidExtractionException('Une période attend une liste « prices » (éventuellement vide).');
        }

        if (null !== $minimumNights && !\is_int($minimumNights)) {
            throw new InvalidExtractionException('« minimumNights » doit être un entier ou null.');
        }

        return new self(
            \is_string($data['label'] ?? null) ? $data['label'] : '',
            ExtractionDates::parse($data['start'] ?? null, 'start', true),
            ExtractionDates::parse($data['end'] ?? null, 'end', true),
            array_map(
                static fn (mixed $price): ExtractedPrice => \is_array($price)
                    ? ExtractedPrice::fromArray($price)
                    : throw new InvalidExtractionException('Chaque prix est un objet { amount, unit }.'),
                $prices,
            ),
            $minimumNights,
            true === ($data['saturdayArrival'] ?? false),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'start' => $this->start?->format('Y-m-d'),
            'end' => $this->end?->format('Y-m-d'),
            'prices' => array_map(static fn (ExtractedPrice $price): array => $price->toArray(), $this->prices),
            'minimumNights' => $this->minimumNights,
            'saturdayArrival' => $this->saturdayArrival,
        ];
    }

    /**
     * Everything that matters for a comparison; the label is only there to help a human.
     */
    public function key(): string
    {
        $prices = array_map(static fn (ExtractedPrice $price): string => $price->key(), $this->prices);
        sort($prices);

        return \sprintf(
            '%s→%s [%s] min=%s%s',
            ExtractionDates::format($this->start),
            ExtractionDates::format($this->end),
            implode(', ', $prices),
            $this->minimumNights ?? '-',
            $this->saturdayArrival ? ' samedi' : '',
        );
    }
}
