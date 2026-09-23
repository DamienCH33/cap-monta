<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * The closed list of equipment (ADR 018). Keys never change: they are stored on accommodations
 * and appear in shared search links. New ones may be added.
 *
 * The front keeps the labels and groups in web/src/app/core/models/search-filters.ts;
 * AmenityListSyncTest fails if the two lists drift apart.
 */
enum Amenity: string
{
    case AirConditioning = 'climatisation';
    case Heating = 'chauffage';
    case Television = 'television';
    case Wifi = 'wifi';
    case Dishwasher = 'lave-vaisselle';
    case Microwave = 'micro-ondes';
    case Oven = 'four';
    case CoffeeMaker = 'cafetiere';
    case Terrace = 'terrasse';
    case CoveredTerrace = 'terrasse-couverte';
    case GardenFurniture = 'salon-de-jardin';
    case Plancha = 'plancha';
    case Barbecue = 'barbecue';
    case OutdoorShower = 'douche-exterieure';
    case WashingMachine = 'lave-linge';
    case Parking = 'parking';
    case LinenProvided = 'linge-fourni';
    case BabyCot = 'lit-bebe';
    case Bikes = 'velos';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
