<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Pricing;

use App\Entity\Accommodation;
use App\Entity\PricePeriod;
use App\Entity\User;
use App\Enum\AccommodationType;
use App\Enum\Resort;
use App\Service\Pricing\PriceCalculator;
use PHPUnit\Framework\TestCase;

final class PriceCalculatorTest extends TestCase
{
    private PriceCalculator $calculator;
    private Accommodation $accommodation;

    protected function setUp(): void
    {
        $this->calculator = new PriceCalculator();
        $this->accommodation = new Accommodation(
            'mobile-home-pins',
            Resort::Chm,
            AccommodationType::MobileHome,
            4,
            2,
            'Test accommodation',
            new User('pricing@example.com', 'Proprietaire test'),
        );
    }

    public function testAFullWeekIsChargedAtTheWeeklyRate(): void
    {
        $period = $this->period('2026-07-01', '2026-08-01', weekly: 40000);

        $price = $this->calculator->calculate([$period], $this->date('2026-07-01'), $this->date('2026-07-08'));

        self::assertSame(40000, $price);
    }

    public function testAWeeklyOnlyPeriodRoundsUpToFullWeeks(): void
    {
        $period = $this->period('2026-07-01', '2026-08-01', weekly: 40000);

        // 9 nights, no nightly rate published: two weeks.
        $price = $this->calculator->calculate([$period], $this->date('2026-07-01'), $this->date('2026-07-10'));

        self::assertSame(80000, $price);
    }

    public function testExtraNightsUseTheNightlyRateWhenPublished(): void
    {
        $period = $this->period('2026-07-01', '2026-08-01', weekly: 40000, nightly: 7000);

        // 9 nights: one full week plus two nights.
        $price = $this->calculator->calculate([$period], $this->date('2026-07-01'), $this->date('2026-07-10'));

        self::assertSame(40000 + 2 * 7000, $price);
    }

    public function testAStayCrossingTwoPeriodsIsSplit(): void
    {
        $low = $this->period('2026-07-01', '2026-07-15', weekly: 40000, nightly: 7000);
        $high = $this->period('2026-07-15', '2026-08-01', weekly: 55000, nightly: 9000);

        // Nights of 13 and 14 July in low season, 15, 16 and 17 in high season.
        $price = $this->calculator->calculate([$low, $high], $this->date('2026-07-13'), $this->date('2026-07-18'));

        self::assertSame(2 * 7000 + 3 * 9000, $price);
    }

    public function testANightOutsideEveryPeriodMakesThePriceUnknown(): void
    {
        $period = $this->period('2026-07-01', '2026-08-01', weekly: 40000, nightly: 7000);

        $price = $this->calculator->calculate([$period], $this->date('2026-06-28'), $this->date('2026-07-03'));

        self::assertNull($price);
    }

    public function testAnAccommodationWithoutAnyPeriodHasNoPrice(): void
    {
        $price = $this->calculator->calculate([], $this->date('2026-07-01'), $this->date('2026-07-08'));

        self::assertNull($price);
    }

    private function period(string $start, string $end, ?int $weekly = null, ?int $nightly = null): PricePeriod
    {
        $period = new PricePeriod($this->accommodation, $this->date($start), $this->date($end));
        $period->setWeeklyPrice($weekly);
        $period->setNightlyPrice($nightly);

        return $period;
    }

    private function date(string $date): \DateTimeImmutable
    {
        return new \DateTimeImmutable($date);
    }
}
