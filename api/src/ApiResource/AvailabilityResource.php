<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\State\AvailabilityProvider;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;

#[ApiResource(
    // Lecture publique : le rendu serveur la transmet au navigateur (pas de second appel), et un
    // cache partagé peut la garder un peu. « private » ou « no-cache » empêcheraient les deux.
    cacheHeaders: ['max_age' => 60, 'shared_max_age' => 60, 'public' => true],
    shortName: 'Availability',
    operations: [
        new Get(
            uriTemplate: '/accommodations/{slug}/availability',
            provider: AvailabilityProvider::class,
        ),
    ],
)]
final class AvailabilityResource
{
    /**
     * @param list<BusyPeriod> $busy
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $slug,
        #[Context(normalizationContext: [DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
        public \DateTimeImmutable $from,
        #[Context(normalizationContext: [DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
        public \DateTimeImmutable $to,
        public array $busy,
    ) {
    }
}
