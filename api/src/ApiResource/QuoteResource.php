<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use App\Service\Booking\Quote;
use App\State\QuoteProvider;

/**
 * Ce que coûterait un séjour, avant toute demande.
 */
#[ApiResource(
    shortName: 'Quote',
    operations: [
        new Get(
            uriTemplate: '/accommodations/{slug}/quote',
            parameters: [
                'arrival' => new QueryParameter(
                    description: "Jour d'arrivée, inclus, format YYYY-MM-DD",
                    schema: ['type' => 'string', 'format' => 'date'],
                    required: true,
                ),
                'departure' => new QueryParameter(
                    description: 'Jour du départ, exclu, format YYYY-MM-DD',
                    schema: ['type' => 'string', 'format' => 'date'],
                    required: true,
                ),
                'guests' => new QueryParameter(
                    description: 'Nombre de personnes, adultes et enfants',
                    schema: ['type' => 'integer', 'minimum' => 1],
                ),
                'pets' => new QueryParameter(
                    description: 'Nombre d’animaux : exclut les logements qui ne les acceptent pas',
                    schema: ['type' => 'integer', 'minimum' => 0],
                ),
            ],
            provider: QuoteProvider::class,
        ),
    ],
)]
final class QuoteResource
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $slug,
        public \DateTimeImmutable $arrival,
        public \DateTimeImmutable $departure,
        public int $nights,
        public int $guests,
        public int $maxCapacity,
        public int $minimumNights,
        public bool $available,
        public ?int $total,
        public ?string $refusal,
        /** The owner prefers another arrival day on this period: said, never blocking. */
        public bool $outsideRules = false,
    ) {
    }

    public static function fromQuote(
        string $slug,
        \DateTimeImmutable $arrival,
        \DateTimeImmutable $departure,
        Quote $quote,
    ): self {
        return new self(
            $slug,
            $arrival,
            $departure,
            $quote->nights,
            $quote->guests,
            $quote->maxCapacity,
            $quote->minimumNights,
            $quote->isAvailable(),
            $quote->total,
            $quote->refusal?->value,
            $quote->outsideRules,
        );
    }
}
