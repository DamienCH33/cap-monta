<?php

declare(strict_types=1);

namespace App\State;

use App\Enum\Resort;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Search parameters read from the query string, validated once.
 */
final readonly class StayQuery
{
    private function __construct(
        public ?\DateTimeImmutable $arrival,
        public ?\DateTimeImmutable $departure,
        public int $guests,
        public ?Resort $resort,
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

        return new self($arrival, $departure, $guests, $resort);
    }

    public function hasDates(): bool
    {
        return null !== $this->arrival && null !== $this->departure;
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
