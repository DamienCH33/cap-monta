<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Accommodation;
use App\Entity\User;
use App\Enum\AccommodationStatus;
use App\Enum\DistrictArea;
use App\Enum\Resort;
use App\Enum\UnavailabilitySource;
use App\Factory\AccommodationFactory;
use App\Factory\DistrictFactory;
use App\Factory\PricePeriodFactory;
use App\Factory\UnavailabilityFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Jeu de données de développement : une douzaine de logements, leurs grilles
 * tarifaires et un calendrier crédible.
 */
final class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
    ) {
    }
    /**
     * The 21 districts of the official CHM site plan (2024).
     * Area read visually on the plan: to be confirmed on site. Null = not settled yet.
     */
    private const CHM_DISTRICTS = [
        ['Sables', DistrictArea::Dunes],
        ['Ajoncs', DistrictArea::Dunes],
        ['La Lande', DistrictArea::Dunes],
        ['Europa', DistrictArea::Dunes],
        ['Floride', DistrictArea::Dunes],
        ['Pins', null],
        ['Californie', null],
        ['Gironde', DistrictArea::Central],
        ['Écureuils', DistrictArea::Central],
        ['Bruyères', DistrictArea::Central],
        ['Atlantique', DistrictArea::Central],
        ['Clairvie', DistrictArea::Central],
        ['Soleil', DistrictArea::Central],
        ['Polynésie', DistrictArea::Central],
        ['Caraïbe', DistrictArea::Roadside],
        ['Gascogne', DistrictArea::Roadside],
        ['Guyane', DistrictArea::Roadside],
        ['Basque', DistrictArea::Roadside],
        ['Hawaï', DistrictArea::Roadside],
        ['Médoc', DistrictArea::Roadside],
        ['Verdure', DistrictArea::Roadside],
    ];

    public function load(ObjectManager $manager): void
    {
        $damien = new User('proprietaire@example.com', 'Damien C.');
        $damien->setPassword($this->hasher->hashPassword($damien, 'motdepasse'));
        $damien->verifyEmail(new \DateTimeImmutable());
        $manager->persist($damien);
        $manager->flush();

        $districts = [];

        foreach (self::CHM_DISTRICTS as $position => [$name, $area]) {
            $districts[] = DistrictFactory::createOne([
                'name' => $name,
                'resort' => Resort::Chm,
                'area' => $area,
                'position' => $position,
            ]);
        }

        /** @var list<Accommodation> $accommodations */
        $accommodations = [];

        foreach ($districts as $position => $district) {
            // De 2 à 5 logements selon le quartier, toujours les mêmes d'un rechargement à l'autre.
            array_push(
                $accommodations,
                ...AccommodationFactory::createMany(2 + $position % 4, ['district' => $district]),
            );
        }

        AccommodationFactory::createOne([
            'owner' => $damien,
            'district' => $districts[3],
            'slug' => 'brouillon-europa',
            'status' => AccommodationStatus::Draft,
        ]);
        // Les quartiers d'Euronat ne sont pas encore référencés.

        $damiensListings = AccommodationFactory::createMany(3, [
            'owner' => $damien,
            'district' => $districts[3],
        ]);
        array_push($accommodations, ...$damiensListings);
        array_push($accommodations, ...AccommodationFactory::createMany(3, ['resort' => Resort::Euronat]));

        foreach ($accommodations as $index => $accommodation) {
            // Le premier reste sans tarif publié : la carte doit afficher
            // « Nous consulter » et la recherche ne doit pas le perdre pour autant.
            if (0 !== $index) {
                $this->publishRates($accommodation, 35000 + ($index % 12) * 3000);
            }

            $this->fillCalendar($accommodation, $index);

            // Trois propriétaires sur quatre tiennent leur calendrier : le badge
            // « Calendrier à jour » doit apparaître sur certaines cartes, pas sur toutes.
            if (3 !== $index % 4) {
                $accommodation->markCalendarChecked(new \DateTimeImmutable(sprintf('-%d days', $index % 20)));
            }
        }

        // Un calendrier du compte de test oublié depuis plus d'un mois : « Vos calendriers »
        // du tableau de bord doit montrer les deux états.
        $damiensListings[1]->markCalendarChecked(new \DateTimeImmutable('-45 days'));

        $manager->flush();
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
