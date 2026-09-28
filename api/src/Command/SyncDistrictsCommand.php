<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\District;
use App\Enum\Resort;
use App\Reference\ChmDistricts;
use App\Repository\DistrictRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Creates the CHM districts missing from the database. Runs on every deployment, after the
 * migrations: without districts, no CHM listing can be created. Never changes nor deletes an
 * existing district, so a text or an area corrected by hand stays as it is.
 */
#[AsCommand(name: 'app:districts:sync', description: 'Crée les quartiers du CHM absents de la base')]
final readonly class SyncDistrictsCommand
{
    public function __construct(
        private DistrictRepository $districts,
        private EntityManagerInterface $em,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $known = [];

        foreach ($this->districts->findBy(['resort' => Resort::Chm]) as $district) {
            $known[$district->getName()] = true;
        }

        $created = 0;

        foreach (ChmDistricts::ALL as $position => [$name, $area]) {
            if (isset($known[$name])) {
                continue;
            }

            $district = new District(ChmDistricts::slug($name), $name, Resort::Chm);
            $district->setArea($area)->setPosition($position);
            $this->em->persist($district);
            ++$created;
        }

        $this->em->flush();
        $io->success(0 === $created ? 'Quartiers déjà à jour.' : sprintf('%d quartier(s) créé(s).', $created));

        return 0;
    }
}
