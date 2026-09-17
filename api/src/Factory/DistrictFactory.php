<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\District;
use App\Enum\Resort;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Zenstruck\Foundry\Object\Instantiator;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<District>
 */
final class DistrictFactory extends PersistentObjectFactory
{
    #[\Override]
    public static function class(): string
    {
        return District::class;
    }

    /**
     * The district with that name, created on first use. Tests call it several times with the same name.
     */
    public static function named(string $name): District
    {
        return self::findOrCreate(['name' => $name]);
    }

    #[\Override]
    protected function defaults(): array|callable
    {
        return static fn (): array => [
            'name' => ucfirst(self::faker()->unique()->word()),
            'resort' => Resort::Chm,
        ];
    }

    #[\Override]
    protected function initialize(): static
    {
        return $this
            ->instantiateWith(Instantiator::withConstructor())
            ->beforeInstantiate(static function (array $attributes): array {
                // "La Lande" → "la-lande", "Écureuils" → "ecureuils".
                $attributes['slug'] ??= (new AsciiSlugger('fr'))
                    ->slug((string) $attributes['name'])
                    ->lower()
                    ->toString();

                return $attributes;
            });
    }
}
