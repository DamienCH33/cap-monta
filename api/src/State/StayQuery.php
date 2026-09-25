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
    public const PER_PAGE = 15;

    /** Upper bound for list parameters: nobody ticks more than ten boxes. */
    private const MAX_VALUES = 10;

    /**
     * Upper bounds for the numbers read from the URL. Beyond them the request is absurd, and a
     * huge value (9223372036854775807 guests) used to overflow the price computation into a 500.
     */
    public const MAX_GUESTS = 50;
    private const MAX_PETS = 10;
    private const MAX_BEDROOMS = 20;
    private const MAX_PAGE = 1000;

    /** A seasonal rental at most: longer is not a holiday stay. */
    public const MAX_NIGHTS = 120;

    /** Dates outside this window are typing errors, not stays. */
    private const FIRST_YEAR = 2000;
    private const LAST_YEAR = 2100;

    /**
     * @param list<string>            $districts
     * @param list<AccommodationType> $types
     * @param list<string>            $amenities
     */
    private function __construct(
        public ?\DateTimeImmutable $arrival,
        public ?\DateTimeImmutable $departure,
        public int $guests,
        public int $pets,
        public ?Resort $resort,
        public array $districts,
        public array $types,
        public int $bedrooms,
        public array $amenities,
        public ?string $order,
        public int $page,
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

        if (null !== $arrival && null !== $departure && $departure > $arrival->modify(sprintf('+%d days', self::MAX_NIGHTS))) {
            throw new BadRequestHttpException(sprintf('A stay lasts %d nights at most.', self::MAX_NIGHTS));
        }

        $guests = isset($filters['guests']) ? (int) $filters['guests'] : 1;

        if ($guests < 1 || $guests > self::MAX_GUESTS) {
            throw new BadRequestHttpException(sprintf('"guests" must be between 1 and %d.', self::MAX_GUESTS));
        }

        $pets = isset($filters['pets']) ? (int) $filters['pets'] : 0;

        if ($pets < 0 || $pets > self::MAX_PETS) {
            throw new BadRequestHttpException(sprintf('"pets" must be between 0 and %d.', self::MAX_PETS));
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

        if ($bedrooms < 0 || $bedrooms > self::MAX_BEDROOMS) {
            throw new BadRequestHttpException(sprintf('"bedrooms" must be between 0 and %d.', self::MAX_BEDROOMS));
        }

        $order = $filters['order'] ?? null;

        if (null !== $order && (!is_string($order) || !in_array($order, self::ORDERS, true))) {
            throw new BadRequestHttpException(sprintf('"order" must be one of: %s.', implode(', ', self::ORDERS)));
        }

        $page = isset($filters['page']) ? (int) $filters['page'] : 1;

        if ($page < 1 || $page > self::MAX_PAGE) {
            throw new BadRequestHttpException(sprintf('"page" must be between 1 and %d.', self::MAX_PAGE));
        }

        return new self(
            $arrival,
            $departure,
            $guests,
            $pets,
            $resort,
            self::strings($filters['district'] ?? null, 'district'),
            $types,
            $bedrooms,
            self::strings($filters['amenities'] ?? null, 'amenities'),
            $order,
            $page,
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

        if (false === $date || $date->format('Y-m-d') !== $value) {
            throw new BadRequestHttpException(sprintf('"%s" must be a date formatted YYYY-MM-DD.', $name));
        }

        $year = (int) $date->format('Y');

        if ($year < self::FIRST_YEAR || $year > self::LAST_YEAR) {
            throw new BadRequestHttpException(sprintf('"%s" must be between %d and %d.', $name, self::FIRST_YEAR, self::LAST_YEAR));
        }

        return $date;
    }
}
