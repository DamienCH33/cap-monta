<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AccommodationResource;
use App\ApiResource\PricePeriodResource;
use App\Repository\AccommodationRepository;
use App\Repository\PricePeriodRepository;

/**
 * GET /api/accommodations/{slug}.
 *
 * Returning null is enough: API Platform turns it into a 404.
 *
 * @implements ProviderInterface<AccommodationResource>
 */
final readonly class AccommodationItemProvider implements ProviderInterface
{
    public function __construct(
        private AccommodationRepository $accommodations,
        private PricePeriodRepository $pricePeriods,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?AccommodationResource
    {
        $slug = $uriVariables['slug'] ?? null;

        if (!is_string($slug)) {
            return null;
        }

        $accommodation = $this->accommodations->findOnePublishedBySlug($slug);

        if (null === $accommodation) {
            return null;
        }

        $prices = $this->accommodations->findPriceFromBySlugs([$slug]);

        $resource = AccommodationResource::fromEntity($accommodation, $prices[$slug] ?? null);

        $resource->pricePeriods = array_map(
            PricePeriodResource::fromEntity(...),
            $this->pricePeriods->findUpcoming($accommodation, new \DateTimeImmutable('today')),
        );

        return $resource;
    }
}
