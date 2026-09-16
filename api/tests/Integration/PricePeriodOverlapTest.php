<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Accommodation;
use App\Entity\PricePeriod;
use App\Enum\AccommodationType;
use App\Enum\Resort;
use App\Tests\DatabaseTestCase;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\EntityManagerInterface;

final class PricePeriodOverlapTest extends DatabaseTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testOverlappingPricePeriodsAreRejected(): void
    {
        $accommodation = $this->createAccommodation('mobile-home-tarifs');

        $this->em->persist($this->pricePeriod($accommodation, '2026-07-01', '2026-07-15', 40000));
        $this->em->flush();

        $this->em->persist($this->pricePeriod($accommodation, '2026-07-10', '2026-07-20', 55000));

        $this->expectException(DriverException::class);
        $this->em->flush();
    }

    public function testAdjacentPricePeriodsAreAllowed(): void
    {
        $accommodation = $this->createAccommodation('bungalow-tarifs');

        $this->em->persist($this->pricePeriod($accommodation, '2026-07-01', '2026-07-15', 40000));
        $this->em->persist($this->pricePeriod($accommodation, '2026-07-15', '2026-07-31', 55000));
        $this->em->flush();

        self::assertSame(2, $this->em->getRepository(PricePeriod::class)->count(['accommodation' => $accommodation]));
    }

    private function createAccommodation(string $slug): Accommodation
    {
        $accommodation = new Accommodation($slug, Resort::Chm, AccommodationType::MobileHome, 4, 2, 'Test accommodation');
        $this->em->persist($accommodation);
        $this->em->flush();

        return $accommodation;
    }

    private function pricePeriod(Accommodation $accommodation, string $start, string $end, int $weeklyPrice): PricePeriod
    {
        $period = new PricePeriod($accommodation, new \DateTimeImmutable($start), new \DateTimeImmutable($end));
        $period->setWeeklyPrice($weeklyPrice);

        return $period;
    }
}
