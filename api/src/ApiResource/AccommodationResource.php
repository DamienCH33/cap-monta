<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use App\Entity\Accommodation;
use App\State\AccommodationCollectionProvider;
use App\State\AccommodationItemProvider;

/**
 * What the public API exposes of an accommodation.
 *
 * A resource, not the entity: the owner's email, the internal identifier and the
 * timestamps stay in the domain. Adding a field here is a deliberate act.
 */
#[ApiResource(
    shortName: 'Accommodation',
    operations: [
        new GetCollection(
            uriTemplate: '/accommodations',
            parameters: [
                'arrival' => new QueryParameter(
                    description: "Jour d'arrivée, inclus dans le séjour, format YYYY-MM-DD",
                    schema: ['type' => 'string', 'format' => 'date'],
                ),
                'departure' => new QueryParameter(
                    description: 'Jour du départ, exclu du séjour, format YYYY-MM-DD',
                    schema: ['type' => 'string', 'format' => 'date'],
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
                    description: 'Un ou plusieurs quartiers : district[]=Europa&district[]=Lalande',
                    schema: ['type' => 'array', 'items' => ['type' => 'string']],
                    castToArray: true,
                ),
                'type' => new QueryParameter(
                    description: 'Un ou plusieurs types : type[]=caravan&type[]=bungalow',
                    schema: ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['caravan', 'mobile_home', 'bungalow', 'chalet', 'studio']]],
                    castToArray: true,
                ),
                'bedrooms' => new QueryParameter(
                    description: 'Nombre minimum de chambres',
                    schema: ['type' => 'integer', 'minimum' => 1],
                ),
                'amenities' => new QueryParameter(
                    description: 'Équipements, le logement doit tous les avoir : amenities[]=wifi&amenities[]=terrasse',
                    schema: ['type' => 'array', 'items' => ['type' => 'string']],
                    castToArray: true,
                ),
                'order' => new QueryParameter(
                    description: 'Tri par prix à la semaine, les logements sans tarif en dernier',
                    schema: ['type' => 'string', 'enum' => ['price_asc', 'price_desc']],
                ),
                'page' => new QueryParameter(
                    description: 'Numéro de page, 15 logements par page',
                    schema: ['type' => 'integer', 'minimum' => 1],
                ),
            ],
            provider: AccommodationCollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/accommodations/{slug}',
            provider: AccommodationItemProvider::class,
        ),
    ],
)]
final class AccommodationResource
{
    /**
     * @param list<string>                                       $amenities
     * @param list<array{start: \DateTimeImmutable, free: bool}> $availability
     * @param list<PricePeriodResource>                          $pricePeriods
     * @param list<PhotoResource>                                $photos       detail page only, the cover first
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $slug,
        public string $resort,
        public string $type,
        public ?string $district,
        public int $capacity,
        public int $maxCapacity,
        public int $bedrooms,
        public ?int $surface,
        public array $amenities,
        public string $description,
        public ?int $priceFrom,
        public array $availability = [],
        public array $pricePeriods = [],
        public ?string $districtSlug = null,
        public ?string $districtArea = null,
        public ?PhotoResource $cover = null,
        public array $photos = [],
        /** Checked by the owner less than Accommodation::CALENDAR_FRESHNESS_DAYS ago. */
        public bool $calendarUpToDate = false,
        /** allowed, on_request or not_allowed. */
        public string $petsPolicy = 'on_request',
        /**
         * The owner's conditions and the fees on top of the rent, every value may be null.
         *
         * @var array<string, int|string|null>
         */
        public array $terms = [],
    ) {
    }

    /**
     * @param list<array{start: \DateTimeImmutable, free: bool}> $availability
     */
    public static function fromEntity(Accommodation $accommodation, ?int $priceFrom = null, array $availability = []): self
    {
        return new self(
            $accommodation->getSlug(),
            $accommodation->getResort()->value,
            $accommodation->getType()->value,
            $accommodation->getDistrict()?->getName(),
            $accommodation->getCapacity(),
            $accommodation->getMaxCapacity(),
            $accommodation->getBedrooms(),
            $accommodation->getSurface(),
            $accommodation->getAmenities(),
            $accommodation->getDescription(),
            $priceFrom,
            $availability,
            districtSlug: $accommodation->getDistrict()?->getSlug(),
            districtArea: $accommodation->getDistrict()?->getArea()?->value,
            calendarUpToDate: $accommodation->isCalendarUpToDate(new \DateTimeImmutable()),
            petsPolicy: $accommodation->getPetsPolicy()->value,
            terms: $accommodation->getTerms()->toArray(),
        );
    }
}
