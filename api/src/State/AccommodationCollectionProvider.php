<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AccommodationResource;
use App\Entity\Accommodation;
use App\Repository\AccommodationRepository;

/**
 * GET /api/accommodations.
 *
 * With arrival and departure: only what is actually free for that stay.
 * Without: the whole catalogue.
 *
 * @implements ProviderInterface<AccommodationResource>
 */
final readonly class AccommodationCollectionProvider implements ProviderInterface
{
    public function __construct(private AccommodationRepository $accommodations)
    {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return list<AccommodationResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        /** @var array<string, mixed> $filters */
        $filters = $context['filters'] ?? [];
        $query = StayQuery::fromFilters($filters);

        if ($query->hasDates()) {
            /** @var \DateTimeImmutable $arrival */
            $arrival = $query->arrival;
            /** @var \DateTimeImmutable $departure */
            $departure = $query->departure;

            $accommodations = $this->accommodations->searchAvailable($arrival, $departure, $query->guests, $query->resort);
        } else {
            $criteria = null !== $query->resort ? ['resort' => $query->resort] : [];
            /** @var list<Accommodation> $accommodations */
            $accommodations = $this->accommodations->findBy($criteria, ['slug' => 'ASC']);
        }

        $slugs = array_map(
            static fn (Accommodation $accommodation): string => $accommodation->getSlug(),
            $accommodations,
        );

        $prices = $this->accommodations->findPriceFromBySlugs($slugs);

        return array_map(
            static fn (Accommodation $accommodation): AccommodationResource => AccommodationResource::fromEntity(
                $accommodation,
                $prices[$accommodation->getSlug()] ?? null,
            ),
            $accommodations,
        );
    }
}
