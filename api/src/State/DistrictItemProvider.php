<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\DistrictResource;
use App\Entity\Accommodation;
use App\Repository\AccommodationRepository;
use App\Repository\DistrictRepository;

/**
 * GET /api/districts/{slug}.
 *
 * @implements ProviderInterface<DistrictResource>
 */
final readonly class DistrictItemProvider implements ProviderInterface
{
    public function __construct(
        private DistrictRepository $districts,
        private AccommodationRepository $accommodations,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?DistrictResource
    {
        $slug = $uriVariables['slug'] ?? null;

        if (!is_string($slug)) {
            return null;
        }

        $district = $this->districts->findOneBy(['slug' => $slug]);

        // Null : API Platform répond 404 de lui-même.
        if (null === $district) {
            return null;
        }

        // La même recherche que le public : le nombre affiché est celui qu'on retrouvera en cliquant.
        $accommodations = $this->accommodations->search(districts: [$district->getSlug()]);

        $prices = array_values(array_filter(
            $this->accommodations->findPriceFromBySlugs(array_map(
                static fn (Accommodation $accommodation): string => $accommodation->getSlug(),
                $accommodations,
            )),
            static fn (mixed $price): bool => is_int($price),
        ));

        return DistrictResource::detail(
            $district,
            count($accommodations),
            [] === $prices ? null : min($prices),
            [] === $prices ? null : max($prices),
        );
    }
}
