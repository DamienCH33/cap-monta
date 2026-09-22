<?php

declare(strict_types=1);

namespace App\Service\Pricing;

use App\Entity\PricePeriod;

/**
 * Prices a stay from the rate grid published by the owner.
 *
 * Rules, night by night within each rate period:
 * - every full week costs the weekly rate;
 * - the nights left over cost 1/7 of the weekly rate when the stay lasts a week or more (the
 *   guest rents by the week, a period change in the middle must not make it dearer), and the
 *   nightly rate for a shorter stay, or 1/7 of the weekly rate when there is none;
 * - leftover nights never cost more than a full week.
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

        $stayNights = (int) $arrival->diff($departure)->days;
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
                    $total += $this->priceForNights($currentPeriod, $nights, $stayNights);
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

        return $total + $this->priceForNights($currentPeriod, $nights, $stayNights);
    }

    /**
     * @param list<PricePeriod> $periods
     */
    private function periodCovering(array $periods, \DateTimeImmutable $night): ?PricePeriod
    {
        foreach ($periods as $period) {
            if ($period->range()->contains($night)) {
                return $period;
            }
        }

        return null;
    }

    private function priceForNights(PricePeriod $period, int $nights, int $stayNights): int
    {
        $weekly = $period->getWeeklyPrice();
        $nightly = $period->getNightlyPrice();

        if (null === $weekly) {
            // Guaranteed by the price_period_has_a_price database constraint.
            return $nights * ($nightly ?? throw new \LogicException('A price period must carry at least one rate.'));
        }

        $weeks = intdiv($nights, self::NIGHTS_PER_WEEK);
        $extra = $nights % self::NIGHTS_PER_WEEK;

        $extraPrice = null !== $nightly && $stayNights < self::NIGHTS_PER_WEEK
            ? $extra * $nightly
            : self::proRata($weekly, $extra);

        return $weeks * $weekly + min($extraPrice, $weekly);
    }

    /**
     * A share of the weekly rate, rounded to the euro: 650 € a week, 4 nights → 371 €.
     */
    public static function proRata(int $weekly, int $nights): int
    {
        return (int) round($weekly * $nights / self::NIGHTS_PER_WEEK / 100) * 100;
    }
}
