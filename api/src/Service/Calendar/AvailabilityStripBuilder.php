<?php

declare(strict_types=1);

namespace App\Service\Calendar;

use App\Entity\Unavailability;

/**
 * Projects the unavailabilities of one accommodation onto a few weekly segments,
 * the strip shown on a search result card.
 *
 * Pure computation: no database, no dependency.
 */
final class AvailabilityStripBuilder
{
    public const DEFAULT_WEEKS = 8;

    /**
     * @param list<Unavailability> $unavailabilities
     *
     * @return list<array{start: \DateTimeImmutable, free: bool}>
     */
    public function build(array $unavailabilities, \DateTimeImmutable $from, int $weeks = self::DEFAULT_WEEKS): array
    {
        // Segments always start on a Monday, so two cards are comparable.
        $firstMonday = $from->setTime(0, 0)->modify('monday this week');

        $strip = [];

        for ($week = 0; $week < $weeks; ++$week) {
            $start = $firstMonday->modify(sprintf('+%d weeks', $week));
            $end = $start->modify('+7 days');

            $strip[] = [
                'start' => $start,
                'free' => !$this->isTaken($unavailabilities, $start, $end),
            ];
        }

        return $strip;
    }

    /**
     * A single booked night is enough to grey out the whole week.
     *
     * @param list<Unavailability> $unavailabilities
     */
    private function isTaken(array $unavailabilities, \DateTimeImmutable $start, \DateTimeImmutable $end): bool
    {
        foreach ($unavailabilities as $unavailability) {
            // The overlap rule of the whole project, [) bounds.
            if ($unavailability->getStartDate() < $end && $unavailability->getEndDate() > $start) {
                return true;
            }
        }

        return false;
    }
}
