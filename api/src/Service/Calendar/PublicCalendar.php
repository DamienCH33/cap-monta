<?php

declare(strict_types=1);

namespace App\Service\Calendar;

use App\ApiResource\BusyPeriod;
use App\Entity\Accommodation;
use App\Entity\Unavailability;
use App\Repository\UnavailabilityRepository;
use Psr\Log\LoggerInterface;
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
 *
 * Redis is a speed-up, never a dependency: when it is down, the calendar is read from
 * PostgreSQL directly and the page still shows.
 */
final readonly class PublicCalendar
{
    public function __construct(
        private UnavailabilityRepository $unavailabilities,
        #[Autowire(service: 'calendar.cache')]
        private TagAwareCacheInterface $cache,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return list<BusyPeriod>
     */
    public function busyPeriods(Accommodation $accommodation, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $key = sprintf('calendar.%s.%s.%s', $accommodation->getId()->toRfc4122(), $from->format('Ymd'), $to->format('Ymd'));

        $compute = fn (): array => self::merge($this->unavailabilities->findForPeriod($accommodation, $from, $to));

        try {
            /** @var list<array{0: string, 1: string}> $periods */
            $periods = $this->cache->get($key, function (ItemInterface $item) use ($accommodation, $compute): array {
                $item->tag(self::tag($accommodation));

                return $compute();
            });
        } catch (\Throwable $e) {
            $this->logger->warning('Calendar cache unavailable, read from the database: {message}', ['message' => $e->getMessage(), 'exception' => $e]);
            $periods = $compute();
        }

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
        try {
            $this->cache->invalidateTags([self::tag($accommodation)]);
        } catch (\Throwable $e) {
            // Redis down: nothing was cached meanwhile, and the entries expire within a day anyway.
            $this->logger->error('Calendar cache not invalidated for {slug}: {message}', [
                'slug' => $accommodation->getSlug(),
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);
        }
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
