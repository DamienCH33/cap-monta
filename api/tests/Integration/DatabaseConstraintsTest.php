<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DatabaseConstraintsTest extends KernelTestCase
{
    /**
     * The exclusion constraints cannot be expressed in Doctrine mapping, so every
     * make:migration proposes to drop them. This test fails if one ever disappears.
     */
    public function testExclusionConstraintsAreStillInPlace(): void
    {
        self::bootKernel();
        $connection = self::getContainer()->get(EntityManagerInterface::class)->getConnection();

        $constraints = $connection->fetchFirstColumn(
            "SELECT conname FROM pg_constraint WHERE conrelid IN ('unavailability'::regclass, 'price_period'::regclass)"
        );

        self::assertContains('unavailability_no_overlap', $constraints);
        self::assertContains('unavailability_dates_order', $constraints);
        self::assertContains('price_period_no_overlap', $constraints);
        self::assertContains('price_period_dates_order', $constraints);
        self::assertContains('price_period_has_a_price', $constraints);
    }
}
