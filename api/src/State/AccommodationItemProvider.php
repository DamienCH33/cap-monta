<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AccommodationResource;
use App\Repository\AccommodationRepository;

/**
 * GET /api/accommodations/{slug}.
 *
 * Returning null is enough: API Platform turns it into a 404.
 *
 * @implements ProviderInterface<AccommodationResource>
 */
final readonly class AccommodationItemProvider implements ProviderInterface
{
    public function __construct(private AccommodationRepository $accommodations)
    {
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

        $accommodation = $this->accommodations->findOneBy(['slug' => $slug]);

        return null === $accommodation ? null : AccommodationResource::fromEntity($accommodation);
    }
}
