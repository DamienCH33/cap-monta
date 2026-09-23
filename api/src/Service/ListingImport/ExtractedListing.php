<?php

declare(strict_types=1);

namespace App\Service\ListingImport;

use App\Enum\AccommodationType;
use App\Enum\Amenity;
use App\Enum\PetsPolicy;

/**
 * The accommodation form, as read in the listing. Null means "not written": the form leaves the
 * field empty and the owner fills it. The PHP sorts what the model returns into four boxes:
 *
 * 1. a key of the closed equipment list → $amenities (pre-ticked);
 * 2. anything else worth keeping → $otherFeatures, shown to the owner, left in his description;
 * 3. house rules → their own field ($petsPolicy);
 * 4. an invented key or an impossible number → never stored, listed in $rejected.
 */
final readonly class ExtractedListing
{
    public const int MAX_CAPACITY = 12;
    public const int MAX_BEDROOMS = 6;
    public const int MIN_SURFACE = 5;
    public const int MAX_SURFACE = 200;

    /**
     * @param list<Amenity> $amenities
     * @param list<string>  $otherFeatures
     * @param list<string>  $rejected      what the model returned and the PHP refused
     */
    public function __construct(
        public ?AccommodationType $type = null,
        public ?int $capacity = null,
        public ?int $bedrooms = null,
        public ?int $surface = null,
        public ?string $district = null,
        public array $amenities = [],
        public ?PetsPolicy $petsPolicy = null,
        public array $otherFeatures = [],
        public array $rejected = [],
    ) {
    }

    /**
     * Lenient on purpose: one wrong value must not throw away a whole import. It is dropped and
     * reported instead.
     *
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        // Kept when a saved extraction is read back (evaluation runs).
        $rejected = self::strings($data['rejected'] ?? []);

        $enum = static function (string $field, string $enumClass) use ($data, &$rejected): mixed {
            $value = $data[$field] ?? null;
            if (null === $value) {
                return null;
            }
            $case = \is_string($value) ? $enumClass::tryFrom($value) : null;
            if (null === $case) {
                $rejected[] = \sprintf('%s inconnu : %s', $field, json_encode($value, \JSON_UNESCAPED_UNICODE));
            }

            return $case;
        };

        $number = static function (string $field, int $min, int $max) use ($data, &$rejected): ?int {
            $value = $data[$field] ?? null;
            if (null === $value) {
                return null;
            }
            if (!\is_int($value) || $value < $min || $value > $max) {
                $rejected[] = \sprintf('%s hors limites (%d à %d) : %s', $field, $min, $max, json_encode($value));

                return null;
            }

            return $value;
        };

        $amenities = [];
        foreach (self::strings($data['amenities'] ?? []) as $key) {
            $amenity = Amenity::tryFrom($key);
            if (null === $amenity) {
                $rejected[] = 'équipement hors liste : '.$key;
            } elseif (!\in_array($amenity, $amenities, true)) {
                $amenities[] = $amenity;
            }
        }

        /** @var AccommodationType|null $type */
        $type = $enum('type', AccommodationType::class);
        /** @var PetsPolicy|null $pets */
        $pets = $enum('petsPolicy', PetsPolicy::class);
        $district = $data['district'] ?? null;

        return new self(
            $type,
            $number('capacity', 1, self::MAX_CAPACITY),
            $number('bedrooms', 0, self::MAX_BEDROOMS),
            $number('surface', self::MIN_SURFACE, self::MAX_SURFACE),
            \is_string($district) && '' !== trim($district) ? trim($district) : null,
            $amenities,
            $pets,
            self::strings($data['otherFeatures'] ?? []),
            $rejected,
        );
    }

    /**
     * The district must be one the site knows: matched without case or accents ("hawai" →
     * "Hawaï"), dropped and reported otherwise.
     *
     * @param list<string> $known
     */
    public function keepingOnlyDistricts(array $known): self
    {
        if (null === $this->district) {
            return $this;
        }

        $fold = static fn (string $name): string => mb_strtolower((string) transliterator_transliterate('Any-Latin; Latin-ASCII', $name));
        $match = null;
        foreach ($known as $name) {
            if ($fold($name) === $fold($this->district)) {
                $match = $name;
            }
        }

        return new self(
            $this->type, $this->capacity, $this->bedrooms, $this->surface, $match,
            $this->amenities, $this->petsPolicy, $this->otherFeatures,
            null === $match ? [...$this->rejected, 'quartier inconnu : '.$this->district] : $this->rejected,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type?->value,
            'capacity' => $this->capacity,
            'bedrooms' => $this->bedrooms,
            'surface' => $this->surface,
            'district' => $this->district,
            'amenities' => array_map(static fn (Amenity $amenity): string => $amenity->value, $this->amenities),
            'petsPolicy' => $this->petsPolicy?->value,
            'otherFeatures' => $this->otherFeatures,
            'rejected' => $this->rejected,
        ];
    }

    /**
     * @return list<string>
     */
    private static function strings(mixed $values): array
    {
        if (!\is_array($values)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $value): string => \is_string($value) ? trim($value) : '', $values),
            static fn (string $value): bool => '' !== $value,
        ));
    }
}
