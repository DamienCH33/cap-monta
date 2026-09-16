<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Accommodation;
use App\Enum\Resort;
use App\Enum\UnavailabilitySource;
use App\Factory\AccommodationFactory;
use App\Factory\PricePeriodFactory;
use App\Factory\UnavailabilityFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Jeu de données de développement : une douzaine de logements, leurs grilles
 * tarifaires et un calendrier crédible.
 */
final class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        /** @var list<Accommodation> $accommodations */
        $accommodations = [
            ...AccommodationFactory::createMany(9),
            ...AccommodationFactory::createMany(3, [
                'resort' => Resort::Euronat,
                'district' => 'Euronat',
            ]),
        ];

        foreach ($accommodations as $index => $accommodation) {
            // Le premier reste sans tarif publié : la carte doit afficher
            // « Nous consulter » et la recherche ne doit pas le perdre pour autant.
            if (0 !== $index) {
                $this->publishRates($accommodation, 35000 + $index * 3000);
            }

            $this->fillCalendar($accommodation, $index);
        }
    }

    /**
     * Quatre périodes par saison, sur la saison en cours et la suivante.
     */
    private function publishRates(Accommodation $accommodation, int $lowSeasonWeekly): void
    {
        $currentYear = (int) date('Y');

        foreach ([$currentYear, $currentYear + 1] as $index => $season) {
            // Les tarifs de la saison suivante sont revalorisés de 3 %.
            $base = (int) round($lowSeasonWeekly * (1 + 0.03 * $index));

            $periods = [
                ['04-01', '06-28', $base, 3],
                ['06-28', '07-12', (int) round($base * 1.6), 7],
                ['07-12', '08-24', (int) round($base * 2.0), 7],
                ['08-24', '10-01', (int) round($base * 1.2), 3],
            ];

            foreach ($periods as [$start, $end, $weekly, $minimumNights]) {
                PricePeriodFactory::createOne([
                    'accommodation' => $accommodation,
                    'startDate' => new \DateTimeImmutable(sprintf('%d-%s', $season, $start)),
                    'endDate' => new \DateTimeImmutable(sprintf('%d-%s', $season, $end)),
                    'weeklyPrice' => (int) round($weekly / 100) * 100,
                    'nightlyPrice' => 7 === $minimumNights
                        ? null
                        : (int) round($weekly / 5 / 100) * 100,
                    'minimumNights' => $minimumNights,
                ]);
            }
        }
    }

    /**
     * Des semaines occupées, à des décalages distincts par construction.
     */
    private function fillCalendar(Accommodation $accommodation, int $index): void
    {
        $offsets = match ($index % 3) {
            0 => [1, 4, 9],
            1 => [2, 3, 7, 12],
            default => [5, 6],
        };

        foreach ($offsets as $offset) {
            UnavailabilityFactory::week($accommodation, $offset);
        }

        // Une semaine bloquée par le propriétaire, pour distinguer les sources.
        UnavailabilityFactory::week($accommodation, 15, UnavailabilitySource::Block);
    }
}
