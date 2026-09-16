<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Accommodation;
use App\Entity\Unavailability;
use App\Enum\UnavailabilitySource;
use Zenstruck\Foundry\Object\Instantiator;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Unavailability>
 */
final class UnavailabilityFactory extends PersistentObjectFactory
{
    #[\Override]
    public static function class(): string
    {
        return Unavailability::class;
    }

    /**
     * Une semaine pleine, du lundi décalé de $offset semaines au lundi suivant.
     *
     * Les plages sont calculées, jamais tirées au sort : deux indisponibilités
     * qui se chevauchent sur un même logement violent la contrainte d'exclusion.
     * Des décalages distincts garantissent des semaines disjointes par construction.
     */
    public static function week(
        Accommodation $accommodation,
        int $offset,
        UnavailabilitySource $source = UnavailabilitySource::Booking,
    ): Unavailability {
        $start = (new \DateTimeImmutable('monday this week'))
            ->modify(sprintf('%+d weeks', $offset));

        return self::createOne([
            'accommodation' => $accommodation,
            'startDate' => $start,
            'endDate' => $start->modify('+7 days'),
            'source' => $source,
        ]);
    }

    #[\Override]
    protected function defaults(): array|callable
    {
        return static function (): array {
            $start = new \DateTimeImmutable('monday next week');

            return [
                'accommodation' => AccommodationFactory::new(),
                'startDate' => $start,
                'endDate' => $start->modify('+7 days'),
                'source' => UnavailabilitySource::Booking,
            ];
        };
    }

    #[\Override]
    protected function initialize(): static
    {
        return $this->instantiateWith(Instantiator::withConstructor());
    }
}
