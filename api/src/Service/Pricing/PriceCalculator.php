<?php

declare(strict_types=1);

namespace App\Service\Pricing;

use App\Entity\PricePeriod;

/**
 * Prices a stay from the rate grid published by the owner.
 *
 * Pure computation: no database, no dependency. The caller fetches the periods
 * (PricePeriodRepository::findCoveringStay) and hands them over.
 */
final class PriceCalculator
{
    private const NIGHTS_PER_WEEK = 7;

    /**
     * @param list<PricePeriod> $periods
     *
     * @return int|null total in cents, or null when at least one night has no published rate
     */
    public function calculate(array $periods, \DateTimeImmutable $arrival, \DateTimeImmutable $departure): ?int
    {
        $arrival = $arrival->setTime(0, 0);
        $departure = $departure->setTime(0, 0);

        $total = 0;
        $currentPeriod = null;
        $nights = 0;

        // [) bounds: DatePeriod excludes the end date, so it yields exactly the nights.
        foreach (new \DatePeriod($arrival, new \DateInterval('P1D'), $departure) as $night) {
            $period = $this->periodCovering($periods, $night);

            if (null === $period) {
                return null;
            }

            if ($period !== $currentPeriod) {
                if (null !== $currentPeriod) {
                    $total += $this->priceForNights($currentPeriod, $nights);
                }

                $currentPeriod = $period;
                $nights = 0;
            }

            ++$nights;
        }

        // No night at all: arrival equals departure, there is nothing to price.
        if (null === $currentPeriod) {
            return null;
        }

        return $total + $this->priceForNights($currentPeriod, $nights);
    }

    /**
     * @param list<PricePeriod> $periods
     */
    private function periodCovering(array $periods, \DateTimeImmutable $night): ?PricePeriod
    {
        foreach ($periods as $period) {
            if ($period->getStartDate() <= $night && $night < $period->getEndDate()) {
                return $period;
            }
        }

        return null;
    }

    private function priceForNights(PricePeriod $period, int $nights): int
    {
        $weekly = $period->getWeeklyPrice();
        $nightly = $period->getNightlyPrice();

        if (null !== $weekly && null !== $nightly) {
            return intdiv($nights, self::NIGHTS_PER_WEEK) * $weekly
                + ($nights % self::NIGHTS_PER_WEEK) * $nightly;
        }

        // Weekly rate only: the owner rents by the week, so we round up.
        if (null !== $weekly) {
            return (int) ceil($nights / self::NIGHTS_PER_WEEK) * $weekly;
        }

        if (null !== $nightly) {
            return $nights * $nightly;
        }

        // Guaranteed by the price_period_has_a_price database constraint.
        throw new \LogicException('A price period must carry at least one rate.');
    }
}
