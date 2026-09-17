<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AccommodationResource;
use App\Entity\Accommodation;
use App\Repository\AccommodationRepository;
use App\Repository\UnavailabilityRepository;
use App\Service\Calendar\AvailabilityStripBuilder;

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
    public function __construct(
        private AccommodationRepository $accommodations,
        private UnavailabilityRepository $unavailabilities,
        private AvailabilityStripBuilder $stripBuilder,
    ) {
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

        $accommodations = $this->accommodations->search(
            arrival: $query->arrival,
            departure: $query->departure,
            guests: $query->guests,
            resort: $query->resort,
            districts: $query->districts,
            types: $query->types,
            bedrooms: $query->bedrooms,
            amenities: $query->amenities,
        );

        $slugs = array_map(
            static fn (Accommodation $accommodation): string => $accommodation->getSlug(),
            $accommodations,
        );

        $prices = $this->accommodations->findPriceFromBySlugs($slugs);

        if (null !== $query->order) {
            $accommodations = $this->sortByPrice($accommodations, $prices, 'price_desc' === $query->order);
        }

        $from = $query->arrival ?? new \DateTimeImmutable('today');
        $to = $from->modify('+9 weeks');

        $busy = $this->unavailabilities->findForPeriodBySlugs($slugs, $from, $to);

        return array_map(
            fn (Accommodation $accommodation): AccommodationResource => AccommodationResource::fromEntity(
                $accommodation,
                $prices[$accommodation->getSlug()] ?? null,
                $this->stripBuilder->build($busy[$accommodation->getSlug()] ?? [], $from),
            ),
            $accommodations,
        );
    }

    /**
     * Accommodations without a published rate always come last, whatever the direction.
     *
     * @param list<Accommodation>     $accommodations
     * @param array<string, int|null> $prices
     *
     * @return list<Accommodation>
     */
    private function sortByPrice(array $accommodations, array $prices, bool $descending): array
    {
        usort($accommodations, static function (Accommodation $a, Accommodation $b) use ($prices, $descending): int {
            $priceA = $prices[$a->getSlug()] ?? null;
            $priceB = $prices[$b->getSlug()] ?? null;

            if ($priceA === $priceB) {
                return strcmp($a->getSlug(), $b->getSlug());
            }

            if (null === $priceA) {
                return 1;
            }

            if (null === $priceB) {
                return -1;
            }

            return $descending ? $priceB <=> $priceA : $priceA <=> $priceB;
        });

        return $accommodations;
    }
}
