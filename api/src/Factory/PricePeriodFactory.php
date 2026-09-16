<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\PricePeriod;
use Zenstruck\Foundry\Object\Instantiator;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<PricePeriod>
 */
final class PricePeriodFactory extends PersistentObjectFactory
{
    #[\Override]
    public static function class(): string
    {
        return PricePeriod::class;
    }

    #[\Override]
    protected function defaults(): array|callable
    {
        return static function (): array {
            // Avril à fin juin : la période par défaut, que l'appelant remplace
            // presque toujours. Aucune date tirée au sort.
            $start = new \DateTimeImmutable('first day of april this year');

            return [
                'accommodation' => AccommodationFactory::new(),
                'startDate' => $start,
                'endDate' => $start->modify('+3 months'),
                'weeklyPrice' => self::faker()->numberBetween(35, 80) * 1000,
                'nightlyPrice' => null,
                'minimumNights' => 3,
            ];
        };
    }

    #[\Override]
    protected function initialize(): static
    {
        return $this->instantiateWith(Instantiator::withConstructor());
    }
}
