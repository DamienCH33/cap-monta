<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AccommodationResource;
use App\ApiResource\PhotoResource;
use App\Entity\Accommodation;
use App\Repository\AccommodationRepository;
use App\Repository\PhotoRepository;
use App\Repository\UnavailabilityRepository;
use App\Service\Calendar\AvailabilityStripBuilder;
use App\Service\Photo\PhotoStorage;

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
        private PhotoRepository $photos,
        private PhotoStorage $storage,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return iterable<AccommodationResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
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
            pets: $query->pets,
        );

        // Les prix servent au tri : il les faut pour toute la liste, pas seulement pour la page.
        $prices = $this->accommodations->findPriceFromBySlugs(array_map(
            static fn (Accommodation $accommodation): string => $accommodation->getSlug(),
            $accommodations,
        ));

        if (null !== $query->order) {
            $accommodations = $this->sortByPrice($accommodations, $prices, 'price_desc' === $query->order);
        }

        $total = count($accommodations);
        $page = array_slice($accommodations, ($query->page - 1) * StayQuery::PER_PAGE, StayQuery::PER_PAGE);

        // Le calcul des disponibilités, lui, ne porte que sur la page affichée.
        $pageSlugs = array_map(
            static fn (Accommodation $accommodation): string => $accommodation->getSlug(),
            $page,
        );

        $from = $query->arrival ?? new \DateTimeImmutable('today');
        $to = $from->modify('+9 weeks');
        $busy = $this->unavailabilities->findForPeriodBySlugs($pageSlugs, $from, $to);
        $covers = $this->photos->findCoversBySlugs($pageSlugs);

        $resources = array_map(
            function (Accommodation $accommodation) use ($prices, $busy, $covers, $from): AccommodationResource {
                $slug = $accommodation->getSlug();
                $resource = AccommodationResource::fromEntity(
                    $accommodation,
                    $prices[$slug] ?? null,
                    $this->stripBuilder->build($busy[$slug] ?? [], $from),
                );

                if (isset($covers[$slug])) {
                    $resource->cover = PhotoResource::fromEntity($covers[$slug], $this->storage);
                }

                return $resource;
            },
            $page,
        );

        return new TraversablePaginator(
            new \ArrayIterator($resources),
            $query->page,
            StayQuery::PER_PAGE,
            $total,
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
