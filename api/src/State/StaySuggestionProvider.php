<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\StaySuggestionResource;
use App\Repository\AccommodationRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @implements ProviderInterface<StaySuggestionResource>
 */
final class StaySuggestionProvider implements ProviderInterface
{
    public function __construct(
        private readonly AccommodationRepository $accommodationRepository,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return list<StaySuggestionResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        /** @var array<string, mixed> $filters */
        $filters = $context['filters'] ?? [];

        $query = StayQuery::fromFilters($filters);

        $arrival = $query->arrival;
        $departure = $query->departure;

        if (null === $arrival || null === $departure) {
            throw new BadRequestHttpException('Both "arrival" and "departure" are required to suggest other dates.');
        }

        $rows = $this->accommodationRepository->findNearestAvailableStays(
            $arrival,
            $departure,
            $this->clock->now(),
            $query->guests,
            $query->resort,
            $query->districts,
            $query->types,
            $query->bedrooms,
            $query->amenities,
            $query->pets,
        );

        return array_map(
            static fn (array $row): StaySuggestionResource => new StaySuggestionResource(
                $row['arrival'],
                $row['departure'],
                $row['available_count'],
            ),
            $rows,
        );
    }
}
