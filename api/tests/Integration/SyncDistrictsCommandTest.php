<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\District;
use App\Enum\DistrictArea;
use App\Enum\Resort;
use App\Factory\DistrictFactory;
use App\Reference\ChmDistricts;
use App\Reference\EuronatDistricts;
use App\Tests\DatabaseTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class SyncDistrictsCommandTest extends DatabaseTestCase
{
    public function testFillsAnEmptyDatabaseOnceAndLeavesExistingDistrictsAlone(): void
    {
        DistrictFactory::createOne(['name' => 'Sables', 'resort' => Resort::Chm, 'area' => DistrictArea::Central, 'intro' => 'Écrit à la main.']);
        DistrictFactory::createOne(['name' => 'Pins', 'resort' => Resort::Chm, 'area' => null]);

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
        self::assertSame(DistrictArea::Central, $em->getRepository(District::class)->findOneBy(['name' => 'Pins'])?->getArea(), 'a district without area gets the one from the plan');
        self::assertSame(DistrictArea::Central, $em->getRepository(District::class)->findOneBy(['name' => 'Californie'])?->getArea());
        self::assertSame('ecureuils', $em->getRepository(District::class)->findOneBy(['name' => 'Écureuils'])?->getSlug());
    }

    public function testCreatesTheEuronatVillagesAndKeepsWhatWasSetByHand(): void
    {
        DistrictFactory::createOne(['name' => 'Europe', 'slug' => 'euronat-europe', 'resort' => Resort::Euronat, 'highlights' => ['Écrit à la main']]);

        $tester = new CommandTester(new Application(self::$kernel)->find('app:districts:sync'));
        self::assertSame(0, $tester->execute([]));
        self::assertSame(0, $tester->execute([]));

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $repository = $em->getRepository(District::class);
        self::assertCount(\count(EuronatDistricts::ALL), $repository->findBy(['resort' => Resort::Euronat]));

        // Polynésie in both resorts: two districts, two slugs.
        self::assertSame('polynesie', $repository->findOneBy(['name' => 'Polynésie', 'resort' => Resort::Chm])?->getSlug());
        $polynesie = $repository->findOneBy(['name' => 'Polynésie', 'resort' => Resort::Euronat]);
        self::assertSame('euronat-polynesie', $polynesie?->getSlug());
        self::assertNull($polynesie->getArea(), 'the CHM areas mean nothing at Euronat');
        self::assertSame('euronat-afrique-ii', $repository->findOneBy(['name' => 'Afrique II'])?->getSlug());
        self::assertCount(\count(ChmDistricts::ALL), $repository->findBy(['resort' => Resort::Chm]), 'the CHM districts are not touched');

        $north = $repository->findOneBy(['slug' => 'euronat-amerique-du-nord']);
        self::assertSame(['Côté plage', 'Accès direct à la plage Nord'], $north?->getHighlights());
        self::assertSame(0, $north->getPosition());
        self::assertSame(['Écrit à la main'], $repository->findOneBy(['slug' => 'euronat-europe'])?->getHighlights(), 'highlights set by hand are kept');
    }
}
