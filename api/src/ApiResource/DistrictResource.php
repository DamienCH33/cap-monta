<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\District;
use App\Enum\Resort;
use App\State\DistrictCollectionProvider;
use App\State\DistrictItemProvider;

#[ApiResource(
    shortName: 'District',
    operations: [
        new GetCollection(
            uriTemplate: '/districts',
            provider: DistrictCollectionProvider::class,
            paginationEnabled: false,
        ),
        new Get(
            uriTemplate: '/districts/{slug}',
            provider: DistrictItemProvider::class,
        ),
    ],
)]
final class DistrictResource
{
    /**
     * @param list<string> $highlights
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $slug,
        public string $name,
        public Resort $resort,
        public ?string $area,
        public int $accommodationCount,
        public ?string $intro = null,
        public array $highlights = [],
        /** Prix hebdomadaire le plus bas du quartier, en centimes. */
        public ?int $priceMin = null,
        /** Prix hebdomadaire le plus haut du quartier, en centimes. */
        public ?int $priceMax = null,
    ) {
    }

    /**
     * Pour la liste : ni texte, ni prix.
     */
    public static function fromEntity(District $district, int $accommodationCount): self
    {
        return new self(
            $district->getSlug(),
            $district->getName(),
            $district->getResort(),
            $district->getArea()?->value,
            $accommodationCount,
        );
    }

    /**
     * Pour la page quartier.
     */
    public static function detail(District $district, int $accommodationCount, ?int $priceMin, ?int $priceMax): self
    {
        return new self(
            $district->getSlug(),
            $district->getName(),
            $district->getResort(),
            $district->getArea()?->value,
            $accommodationCount,
            $district->getIntro(),
            $district->getHighlights(),
            $priceMin,
            $priceMax,
        );
    }
}
