<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\QuoteResource;
use App\Repository\AccommodationRepository;
use App\Service\Booking\QuoteCalculator;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * GET /api/accommodations/{slug}/quote.
 *
 * @implements ProviderInterface<QuoteResource>
 */
final readonly class QuoteProvider implements ProviderInterface
{
    public function __construct(
        private AccommodationRepository $accommodations,
        private QuoteCalculator $quotes,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?QuoteResource
    {
        $slug = $uriVariables['slug'] ?? null;

        if (!is_string($slug)) {
            return null;
        }

        $accommodation = $this->accommodations->findOneBySlug($slug);

        if (null === $accommodation) {
            return null;
        }

        /** @var array<string, mixed> $filters */
        $filters = $context['filters'] ?? [];
        $query = StayQuery::fromFilters($filters);

        if (!$query->hasDates()) {
            throw new BadRequestHttpException('Both "arrival" and "departure" are required for a quote.');
        }

        /** @var \DateTimeImmutable $arrival */
        $arrival = $query->arrival;
        /** @var \DateTimeImmutable $departure */
        $departure = $query->departure;

        $quote = $this->quotes->quote($accommodation, $arrival, $departure, $query->guests);

        return QuoteResource::fromQuote($slug, $arrival, $departure, $quote);
    }
}
