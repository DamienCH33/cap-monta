<?php

declare(strict_types=1);

namespace App\Service\Calendar;

use App\ApiResource\BusyPeriod;
use App\Entity\Accommodation;
use App\Entity\Unavailability;
use App\Repository\UnavailabilityRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * What the public may know of a calendar: which days are taken, never why.
 *
 * A booking, an owner's block and an imported event all become the same "unavailable"
 * period, and periods that touch are merged: two back-to-back entries would otherwise
 * reveal where a booking ends and the owner's own stay begins.
 *
 * Cached in Redis per accommodation and per day (the window starts today). Every change
 * to the calendar goes through invalidate(), which drops all the days at once by tag.
 */
final readonly class PublicCalendar
{
    public function __construct(
        private UnavailabilityRepository $unavailabilities,
        #[Autowire(service: 'calendar.cache')]
        private TagAwareCacheInterface $cache,
    ) {
    }

    /**
     * @return list<BusyPeriod>
     */
    public function busyPeriods(Accommodation $accommodation, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $key = sprintf('calendar.%s.%s.%s', $accommodation->getId()->toRfc4122(), $from->format('Ymd'), $to->format('Ymd'));

        /** @var list<array{0: string, 1: string}> $periods */
        $periods = $this->cache->get($key, function (ItemInterface $item) use ($accommodation, $from, $to): array {
            $item->tag(self::tag($accommodation));

            return self::merge($this->unavailabilities->findForPeriod($accommodation, $from, $to));
        });

        return array_map(
            static fn (array $period): BusyPeriod => new BusyPeriod(
                new \DateTimeImmutable($period[0]),
                new \DateTimeImmutable($period[1]),
            ),
            $periods,
        );
    }

    /** To call after any change to the accommodation's unavailabilities. */
    public function invalidate(Accommodation $accommodation): void
    {
        $this->cache->invalidateTags([self::tag($accommodation)]);
    }

    /**
     * Sorted, touching or overlapping periods merged. Plain strings: that is what goes to Redis.
     *
     * @param list<Unavailability> $unavailabilities
     *
     * @return list<array{0: string, 1: string}>
     */
    public static function merge(array $unavailabilities): array
    {
        usort($unavailabilities, static fn (Unavailability $a, Unavailability $b): int => $a->getStartDate() <=> $b->getStartDate());

        $merged = [];

        foreach ($unavailabilities as $unavailability) {
            $start = $unavailability->getStartDate()->format('Y-m-d');
            $end = $unavailability->getEndDate()->format('Y-m-d');
            $last = array_key_last($merged);

            // ISO dates compare as strings. "<=": a departure on the 15th and an arrival
            // on the 15th leave no free night between them.
            if (null !== $last && $start <= $merged[$last][1]) {
                $merged[$last][1] = max($merged[$last][1], $end);

                continue;
            }

            $merged[] = [$start, $end];
        }

        return $merged;
    }

    private static function tag(Accommodation $accommodation): string
    {
        return 'calendar.'.$accommodation->getId()->toRfc4122();
    }
}
