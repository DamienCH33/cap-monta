<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Accommodation;
use App\Enum\AccommodationStatus;
use App\Enum\AccommodationType;
use App\Enum\Resort;
use Zenstruck\Foundry\Object\Instantiator;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Accommodation>
 */
final class AccommodationFactory extends PersistentObjectFactory
{
    private const AMENITIES = [
        'climatisation', 'chauffage', 'lave-vaisselle', 'micro-ondes', 'four',
        'terrasse', 'terrasse-couverte', 'salon-de-jardin', 'television', 'wifi', 'plancha',
        'barbecue', 'lave-linge', 'parking', 'lit-bebe', 'linge-fourni',
    ];

    #[\Override]
    public static function class(): string
    {
        return Accommodation::class;
    }

    #[\Override]
    protected function defaults(): array|callable
    {
        return static function (): array {
            // The three CHM types only: the fixtures fill the CHM, and their photos exist for these.
            $types = [AccommodationType::Caravan, AccommodationType::MobileHome, AccommodationType::Bungalow];
            $type = $types[array_rand($types)];

            // La capacité découle du type : une caravane n'accueille pas huit personnes.
            [$capacity, $bedrooms, $surface] = match ($type) {
                AccommodationType::Caravan => [4, 1, self::faker()->numberBetween(15, 22)],
                AccommodationType::MobileHome => [6, 2, self::faker()->numberBetween(25, 34)],
                AccommodationType::Bungalow => [8, 3, self::faker()->numberBetween(38, 50)],
            };

            return [
                'slug' => self::faker()->unique()->slug(3),
                'resort' => Resort::Chm,
                'type' => $type,
                'capacity' => $capacity,
                'maxCapacity' => $capacity,
                'bedrooms' => $bedrooms,
                'surface' => $surface,
                'amenities' => array_values(
                    self::faker()->randomElements(self::AMENITIES, self::faker()->numberBetween(2, 5)),
                ),
                'description' => self::faker()->paragraph(),
                'owner' => UserFactory::new(),
                'status' => AccommodationStatus::Published,
            ];
        };
    }

    #[\Override]
    protected function initialize(): static
    {
        return $this->instantiateWith(
            Instantiator::withConstructor()->alwaysForce('status'),
        );
    }
}
