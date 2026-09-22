<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AvailabilityResource;
use App\Repository\AccommodationRepository;
use App\Service\Calendar\PublicCalendar;

/**
 * GET /api/accommodations/{slug}/availability.
 *
 * The twelve months ahead, and the periods already taken inside that window —
 * without saying why they are taken (see PublicCalendar).
 *
 * @implements ProviderInterface<AvailabilityResource>
 */
final readonly class AvailabilityProvider implements ProviderInterface
{
    private const WINDOW = '+12 months';

    public function __construct(
        private AccommodationRepository $accommodations,
        private PublicCalendar $calendar,
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

        $busy = $this->calendar->busyPeriods($accommodation, $from, $to);

        return new AvailabilityResource(
            $accommodation->getSlug(),
            $from,
            $to,
            $busy,
        );
    }
}
