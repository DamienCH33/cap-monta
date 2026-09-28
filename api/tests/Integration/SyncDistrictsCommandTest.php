<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\District;
use App\Enum\DistrictArea;
use App\Enum\Resort;
use App\Factory\DistrictFactory;
use App\Reference\ChmDistricts;
use App\Tests\DatabaseTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class SyncDistrictsCommandTest extends DatabaseTestCase
{
    public function testFillsAnEmptyDatabaseOnceAndLeavesExistingDistrictsAlone(): void
    {
        DistrictFactory::createOne(['name' => 'Sables', 'resort' => Resort::Chm, 'area' => DistrictArea::Central, 'intro' => 'Écrit à la main.']);

        $tester = new CommandTester(new Application(self::$kernel)->find('app:districts:sync'));
        self::assertSame(0, $tester->execute([]));
        self::assertSame(0, $tester->execute([]), 'a second deployment must not fail on duplicates');

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $chm = $em->getRepository(District::class)->findBy(['resort' => Resort::Chm]);
        self::assertCount(\count(ChmDistricts::ALL), $chm);

        $sables = $em->getRepository(District::class)->findOneBy(['name' => 'Sables']);
        self::assertSame(DistrictArea::Central, $sables?->getArea(), 'a district corrected by hand is never overwritten');
        self::assertSame('Écrit à la main.', $sables->getIntro());

        $lande = $em->getRepository(District::class)->findOneBy(['name' => 'La Lande']);
        self::assertSame('la-lande', $lande?->getSlug());
        self::assertSame(DistrictArea::Dunes, $lande->getArea());
        self::assertSame(2, $lande->getPosition());
        self::assertSame('ecureuils', $em->getRepository(District::class)->findOneBy(['name' => 'Écureuils'])?->getSlug());
    }
}
