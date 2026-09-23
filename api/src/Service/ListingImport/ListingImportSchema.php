<?php

declare(strict_types=1);

namespace App\Service\ListingImport;

use App\Enum\AccommodationType;
use App\Enum\Amenity;
use App\Enum\PetsPolicy;
use App\Enum\PriceUnit;

/**
 * The JSON schema the model must answer with (JSON Schema, strict mode): every
 * key present, nothing extra, closed lists as enums. The model cannot even write "jacuzzi" in
 * the equipment list. The PHP checks everything again anyway: the schema is a first fence, not
 * the only one.
 */
final class ListingImportSchema
{
    /**
     * @param list<string> $districts names of the known districts, offered as an enum
     *
     * @return array{type: string, json_schema: array{name: string, strict: bool, schema: array<string, mixed>}}
     */
    public static function responseFormat(array $districts): array
    {
        $date = self::nullable('string', 'Date AAAA-MM-JJ, ou null si le texte ne la donne pas.');

        $period = self::object([
            'label' => ['type' => 'string', 'description' => 'Les mots de l\'annonce pour cette période.'],
            'start' => $date,
            'end' => ['description' => 'Date de fin EXCLUSIVE (jour du départ), ou null.'] + $date,
            'prices' => [
                'type' => 'array',
                'items' => self::object([
                    'amount' => ['type' => 'integer', 'description' => 'Montant en euros, tel qu\'écrit.'],
                    'unit' => ['type' => 'string', 'enum' => self::values(PriceUnit::cases())],
                ]),
            ],
            'minimumNights' => self::nullable('integer', 'Minimum de nuits écrit, ou null.'),
            'saturdayArrival' => ['type' => 'boolean'],
        ]);

        $listing = self::object([
            'type' => self::nullableEnum(self::values(AccommodationType::cases())),
            'capacity' => self::nullable('integer', 'Nombre de personnes écrit, ou null.'),
            'bedrooms' => self::nullable('integer', 'Nombre de chambres, ou null.'),
            'surface' => self::nullable('integer', 'Surface habitable en m², ou null.'),
            'district' => self::nullableEnum($districts),
            'amenities' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => Amenity::values()]],
            'petsPolicy' => self::nullableEnum(self::values(PetsPolicy::cases())),
            'otherFeatures' => ['type' => 'array', 'items' => ['type' => 'string']],
        ]);

        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'listing_import',
                'strict' => true,
                'schema' => self::object([
                    'periods' => ['type' => 'array', 'items' => $period],
                    'unavailable' => ['type' => 'array', 'items' => self::object(['start' => ['type' => 'string'], 'end' => ['type' => 'string']])],
                    'listing' => $listing,
                ]),
            ],
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $properties
     *
     * @return array<string, mixed>
     */
    private static function object(array $properties): array
    {
        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => array_keys($properties),
            'additionalProperties' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function nullable(string $type, string $description): array
    {
        return ['type' => [$type, 'null'], 'description' => $description];
    }

    /**
     * @param list<string> $values
     *
     * @return array<string, mixed>
     */
    private static function nullableEnum(array $values): array
    {
        return ['type' => ['string', 'null'], 'enum' => [...$values, null]];
    }

    /**
     * @param list<\BackedEnum> $cases
     *
     * @return list<string>
     */
    private static function values(array $cases): array
    {
        return array_map(static fn (\BackedEnum $case): string => (string) $case->value, $cases);
    }
}
