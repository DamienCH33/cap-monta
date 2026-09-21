<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AvailabilityResource;
use App\ApiResource\BusyPeriod;
use App\Entity\Unavailability;
use App\Repository\AccommodationRepository;
use App\Repository\UnavailabilityRepository;

/**
 * GET /api/accommodations/{slug}/availability.
 *
 * The twelve months ahead, and the periods already taken inside that window.
 *
 * @implements ProviderInterface<AvailabilityResource>
 */
final readonly class AvailabilityProvider implements ProviderInterface
{
    private const WINDOW = '+12 months';

    public function __construct(
        private AccommodationRepository $accommodations,
        private UnavailabilityRepository $unavailabilities,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?AvailabilityResource
    {
        $slug = $uriVariables['slug'] ?? null;

        if (!is_string($slug)) {
            return null;
        }

        $accommodation = $this->accommodations->findOnePublishedBySlug($slug);

        if (null === $accommodation) {
            return null;
        }

        $from = new \DateTimeImmutable('today');
        $to = $from->modify(self::WINDOW);

        $busy = array_map(
            static fn (Unavailability $unavailability): BusyPeriod => new BusyPeriod(
                $unavailability->getStartDate(),
                $unavailability->getEndDate(),
                $unavailability->getSource()->value,
            ),
            $this->unavailabilities->findForPeriod($accommodation, $from, $to),
        );

        return new AvailabilityResource(
            $accommodation->getSlug(),
            $from,
            $to,
            $busy,
        );
    }
}
