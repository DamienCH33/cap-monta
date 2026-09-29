<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\District;
use App\Enum\Resort;
use App\Reference\ChmDistricts;
use App\Reference\EuronatDistricts;
use App\Repository\DistrictRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Creates the CHM districts and the Euronat sectors missing from the database. Runs on every
 * deployment, after the migrations: without districts, no CHM listing can be created. An
 * existing district only gets its area (CHM) or its highlights (Euronat) when it has none yet;
 * what was set by hand is never overwritten.
 */
#[AsCommand(name: 'app:districts:sync', description: 'Crée les quartiers du CHM et les secteurs d\'Euronat absents de la base')]
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
            $known[$district->getName()] = $district;
        }

        $created = 0;
        $placed = 0;

        foreach (ChmDistricts::ALL as $position => [$name, $area]) {
            if (isset($known[$name])) {
                if (null === $known[$name]->getArea()) {
                    $known[$name]->setArea($area);
                    ++$placed;
                }

                continue;
            }

            $district = new District(ChmDistricts::slug($name), $name, Resort::Chm);
            $district->setArea($area)->setPosition($position);
            $this->em->persist($district);
            ++$created;
        }

        [$euronatCreated, $euronatFilled] = $this->syncEuronat();
        $created += $euronatCreated;

        $this->em->flush();
        $io->success(0 === $created + $placed + $euronatFilled
            ? 'Quartiers déjà à jour.'
            : sprintf('%d quartier(s) créé(s), %d placé(s) dans leur zone, %d complété(s).', $created, $placed, $euronatFilled));

        return 0;
    }

    /**
     * @return array{int, int} created, completed with their highlights
     */
    private function syncEuronat(): array
    {
        $known = [];

        foreach ($this->districts->findBy(['resort' => Resort::Euronat]) as $district) {
            $known[$district->getName()] = $district;
        }

        $created = 0;
        $filled = 0;

        foreach (EuronatDistricts::ALL as $position => [$name, $highlights]) {
            if (isset($known[$name])) {
                if ([] === $known[$name]->getHighlights() && [] !== $highlights) {
                    $known[$name]->setHighlights($highlights);
                    ++$filled;
                }

                continue;
            }

            $district = new District(EuronatDistricts::slug($name), $name, Resort::Euronat);
            $district->setHighlights($highlights)->setPosition($position);
            $this->em->persist($district);
            ++$created;
        }

        return [$created, $filled];
    }
}
