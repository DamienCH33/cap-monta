<?php

declare(strict_types=1);

namespace App\Reference;

use App\Enum\DistrictArea;

/**
 * The 21 districts of the official CHM site plan (2024), in plan order.
 * Area read visually on the plan: to be confirmed on site. Null = not settled yet.
 * Shared by the fixtures and by app:districts:sync, which fills the production database.
 */
final class ChmDistricts
{
    /** @var list<array{string, ?DistrictArea}> */
    public const array ALL = [
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

    /** "La Lande" → "la-lande", "Écureuils" → "ecureuils": same rule as DistrictFactory. */
    public static function slug(string $name): string
    {
        return (new \Symfony\Component\String\Slugger\AsciiSlugger('fr'))->slug($name)->lower()->toString();
    }
}
