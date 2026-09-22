<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Accommodation;
use App\Entity\Unavailability;
use App\Entity\User;
use App\Enum\AccommodationType;
use App\Enum\Resort;
use App\Enum\UnavailabilitySource;
use App\Service\Calendar\PublicCalendar;
use PHPUnit\Framework\TestCase;

final class PublicCalendarTest extends TestCase
{
    private Accommodation $home;

    protected function setUp(): void
    {
        $this->home = new Accommodation('bungalow', Resort::Euronat, AccommodationType::Bungalow, 6, 3, 'Test', new User('a@example.com', 'Owner'));
    }

    public function testTouchingPeriodsBecomeOne(): void
    {
        // A booking then the owner's own stay: the public must not see where one ends.
        self::assertSame([['2027-07-03', '2027-07-17']], PublicCalendar::merge([
            $this->period('2027-07-03', '2027-07-10', UnavailabilitySource::Booking),
            $this->period('2027-07-10', '2027-07-17', UnavailabilitySource::Block),
        ]));
    }

    public function testAFreeNightKeepsTwoPeriodsApart(): void
    {
        self::assertSame([['2027-07-03', '2027-07-10'], ['2027-07-11', '2027-07-17']], PublicCalendar::merge([
            $this->period('2027-07-03', '2027-07-10'),
            $this->period('2027-07-11', '2027-07-17'),
        ]));
    }

    public function testTheOrderOfTheInputDoesNotMatter(): void
    {
        self::assertSame([['2027-07-03', '2027-07-24']], PublicCalendar::merge([
            $this->period('2027-07-17', '2027-07-24'),
            $this->period('2027-07-03', '2027-07-10'),
            $this->period('2027-07-10', '2027-07-17'),
        ]));
    }

    public function testAPeriodInsideAnotherChangesNothing(): void
    {
        // Cannot happen in the database (exclusion constraint), but the merge must not shrink.
        self::assertSame([['2027-07-01', '2027-07-31']], PublicCalendar::merge([
            $this->period('2027-07-01', '2027-07-31'),
            $this->period('2027-07-05', '2027-07-06'),
        ]));
    }

    private function period(string $start, string $end, UnavailabilitySource $source = UnavailabilitySource::Block): Unavailability
    {
        return new Unavailability($this->home, new \DateTimeImmutable($start), new \DateTimeImmutable($end), $source);
    }
}
