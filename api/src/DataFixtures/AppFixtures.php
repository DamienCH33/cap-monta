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
     * Quatre périodes qui se suivent, du printemps à la fin septembre.
     */
    private function publishRates(Accommodation $accommodation, int $lowSeasonWeekly): void
    {
        $year = (int) date('Y');

        $periods = [
            ['04-01', '06-28', $lowSeasonWeekly, 3],
            ['06-28', '07-12', (int) round($lowSeasonWeekly * 1.6), 7],
            ['07-12', '08-24', (int) round($lowSeasonWeekly * 2.0), 7],
            ['08-24', '10-01', (int) round($lowSeasonWeekly * 1.2), 3],
        ];

        foreach ($periods as [$start, $end, $weekly, $minimumNights]) {
            PricePeriodFactory::createOne([
                'accommodation' => $accommodation,
                'startDate' => new \DateTimeImmutable(sprintf('%d-%s', $year, $start)),
                'endDate' => new \DateTimeImmutable(sprintf('%d-%s', $year, $end)),
                'weeklyPrice' => $weekly,
                'nightlyPrice' => 7 === $minimumNights ? null : intdiv($weekly, 5),
                'minimumNights' => $minimumNights,
            ]);
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
