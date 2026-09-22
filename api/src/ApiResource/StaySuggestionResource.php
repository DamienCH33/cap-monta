<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use App\State\StaySuggestionProvider;

#[GetCollection(
    uriTemplate: '/stay-suggestions',
    provider: StaySuggestionProvider::class,
    paginationEnabled: false,
    parameters: [
        'arrival' => new QueryParameter(
            description: "Jour d'arrivée, inclus dans le séjour, format YYYY-MM-DD",
            schema: ['type' => 'string', 'format' => 'date'],
            required: true,
        ),
        'departure' => new QueryParameter(
            description: 'Jour du départ, exclu du séjour, format YYYY-MM-DD',
            schema: ['type' => 'string', 'format' => 'date'],
            required: true,
        ),
        'resort' => new QueryParameter(
            description: 'Domaine : CHM Montalivet ou Euronat',
            schema: ['type' => 'string', 'enum' => ['chm', 'euronat']],
        ),
        'guests' => new QueryParameter(
            description: 'Nombre de personnes',
            schema: ['type' => 'integer', 'minimum' => 1],
        ),
        'pets' => new QueryParameter(
            description: 'Nombre d’animaux : exclut les logements qui ne les acceptent pas',
            schema: ['type' => 'integer', 'minimum' => 0],
        ),
        'district' => new QueryParameter(
            description: 'Quartiers, tels que renvoyés par /api/districts',
            schema: ['type' => 'array', 'items' => ['type' => 'string']],
            castToArray: true,
        ),
        'type' => new QueryParameter(
            description: 'Types de logement',
            schema: ['type' => 'array', 'items' => ['type' => 'string']],
            castToArray: true,
        ),
        'bedrooms' => new QueryParameter(
            description: 'Nombre minimum de chambres',
            schema: ['type' => 'integer', 'minimum' => 0],
        ),
        'amenities' => new QueryParameter(
            description: 'Équipements que le logement doit tous avoir',
            schema: ['type' => 'array', 'items' => ['type' => 'string']],
            castToArray: true,
        ),
    ],
)]
final class StaySuggestionResource
{
    public function __construct(
        public readonly string $arrival,
        public readonly string $departure,
        public readonly int $availableCount,
    ) {
    }
}
