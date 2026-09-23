<?php

declare(strict_types=1);

namespace App\Service\ListingImport\Evaluation;

use App\Enum\Amenity;
use App\Service\ListingImport\ExtractionDates;
use App\Service\ListingImport\InvalidExtractionException;
use App\Service\ListingImport\ListingExtraction;

/**
 * One listing of the evaluation set: the text as an owner would paste it, the date it was
 * published (a month without a year belongs to that year), and the answer a careful human
 * would give. $optionalAmenities are the doubtful ones ("table et salon" on a terrace: garden
 * furniture or not?): ticking them or not is both accepted.
 */
final readonly class EvalCase
{
    public function __construct(
        public string $id,
        public string $text,
        public \DateTimeImmutable $publishedAt,
        public ListingExtraction $expected,
        public bool $needsClarification,
        public string $notes = '',
        /** @var list<Amenity> */
        public array $optionalAmenities = [],
    ) {
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $expected = $data['expected'] ?? null;

        if (!\is_string($data['id'] ?? null) || !\is_string($data['text'] ?? null) || !\is_array($expected)) {
            throw new InvalidExtractionException('Un cas attend « id », « text » et « expected ».');
        }

        if (!\is_bool($expected['needsClarification'] ?? null)) {
            throw new InvalidExtractionException(\sprintf('%s : « expected.needsClarification » doit valoir true ou false.', $data['id']));
        }

        $publishedAt = ExtractionDates::parse($data['publishedAt'] ?? null, 'publishedAt', false);
        \assert(null !== $publishedAt);

        return new self(
            $data['id'],
            $data['text'],
            $publishedAt,
            ListingExtraction::fromArray($expected),
            $expected['needsClarification'],
            \is_string($data['notes'] ?? null) ? $data['notes'] : '',
            self::optionalAmenities($expected['listing'] ?? null),
        );
    }

    /**
     * @return list<Amenity>
     */
    private static function optionalAmenities(mixed $listing): array
    {
        $keys = \is_array($listing) && \is_array($listing['optionalAmenities'] ?? null) ? $listing['optionalAmenities'] : [];

        return array_values(array_map(
            static fn (mixed $key): Amenity => (\is_string($key) ? Amenity::tryFrom($key) : null)
                ?? throw new InvalidExtractionException('Équipement optionnel inconnu : '.json_encode($key)),
            $keys,
        ));
    }
}
