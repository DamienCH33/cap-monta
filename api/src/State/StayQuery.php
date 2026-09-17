<?php

declare(strict_types=1);

namespace App\State;

use App\Enum\AccommodationType;
use App\Enum\Resort;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Search parameters read from the query string, validated once.
 */
final readonly class StayQuery
{
    public const ORDERS = ['price_asc', 'price_desc'];

    /** Upper bound for list parameters: nobody ticks more than ten boxes. */
    private const MAX_VALUES = 10;

    /**
     * @param list<string>            $districts
     * @param list<AccommodationType> $types
     * @param list<string>            $amenities
     */
    private function __construct(
        public ?\DateTimeImmutable $arrival,
        public ?\DateTimeImmutable $departure,
        public int $guests,
        public ?Resort $resort,
        public array $districts,
        public array $types,
        public int $bedrooms,
        public array $amenities,
        public ?string $order,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     */
    public static function fromFilters(array $filters): self
    {
        $arrival = self::date($filters['arrival'] ?? null, 'arrival');
        $departure = self::date($filters['departure'] ?? null, 'departure');

        if ((null === $arrival) !== (null === $departure)) {
            throw new BadRequestHttpException('Both "arrival" and "departure" are required to search on dates.');
        }

        if (null !== $arrival && null !== $departure && $departure <= $arrival) {
            throw new BadRequestHttpException('"departure" must come after "arrival".');
        }

        $guests = isset($filters['guests']) ? (int) $filters['guests'] : 1;

        if ($guests < 1) {
            throw new BadRequestHttpException('"guests" must be at least 1.');
        }

        $resort = null;

        if (isset($filters['resort']) && is_string($filters['resort'])) {
            $resort = Resort::tryFrom($filters['resort'])
                ?? throw new BadRequestHttpException(sprintf('Unknown resort "%s".', $filters['resort']));
        }

        $types = array_map(
            static fn (string $value): AccommodationType => AccommodationType::tryFrom($value)
                ?? throw new BadRequestHttpException(sprintf('Unknown accommodation type "%s".', $value)),
            self::strings($filters['type'] ?? null, 'type'),
        );

        $bedrooms = isset($filters['bedrooms']) ? (int) $filters['bedrooms'] : 0;

        if ($bedrooms < 0) {
            throw new BadRequestHttpException('"bedrooms" cannot be negative.');
        }

        $order = $filters['order'] ?? null;

        if (null !== $order && (!is_string($order) || !in_array($order, self::ORDERS, true))) {
            throw new BadRequestHttpException(sprintf('"order" must be one of: %s.', implode(', ', self::ORDERS)));
        }

        return new self(
            $arrival,
            $departure,
            $guests,
            $resort,
            self::strings($filters['district'] ?? null, 'district'),
            $types,
            $bedrooms,
            self::strings($filters['amenities'] ?? null, 'amenities'),
            $order,
        );
    }

    public function hasDates(): bool
    {
        return null !== $this->arrival && null !== $this->departure;
    }

    /**
     * Accepts a single value (district=Europa) or a list (district[]=Europa&district[]=Lalande).
     *
     * @return list<string>
     */
    private static function strings(mixed $value, string $name): array
    {
        if (null === $value || '' === $value) {
            return [];
        }

        $values = is_array($value) ? $value : [$value];

        if (count($values) > self::MAX_VALUES) {
            throw new BadRequestHttpException(sprintf('"%s" accepts at most %d values.', $name, self::MAX_VALUES));
        }

        $clean = [];

        foreach ($values as $item) {
            if (!is_string($item)) {
                throw new BadRequestHttpException(sprintf('"%s" must contain text values.', $name));
            }

            $item = trim($item);

            if ('' !== $item) {
                $clean[] = $item;
            }
        }

        return array_values(array_unique($clean));
    }

    private static function date(mixed $value, string $name): ?\DateTimeImmutable
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if (!is_string($value)) {
            throw new BadRequestHttpException(sprintf('"%s" must be a date formatted YYYY-MM-DD.', $name));
        }

        // The leading "!" resets the time to midnight instead of keeping the current one.
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if (false === $date) {
            throw new BadRequestHttpException(sprintf('"%s" must be a date formatted YYYY-MM-DD.', $name));
        }

        return $date;
    }
}
