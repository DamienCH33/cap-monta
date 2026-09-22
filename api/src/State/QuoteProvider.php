<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\QuoteResource;
use App\Repository\AccommodationRepository;
use App\Service\Booking\QuoteCalculator;
use App\Service\Http\FloodGuard;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

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
        private FloodGuard $floodGuard,
        #[Target('quotes')]
        private RateLimiterFactoryInterface $quotesLimiter,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?QuoteResource
    {
        $this->floodGuard->check($this->quotesLimiter);

        $slug = $uriVariables['slug'] ?? null;

        if (!is_string($slug)) {
            return null;
        }

        $accommodation = $this->accommodations->findOnePublishedBySlug($slug);

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

        $quote = $this->quotes->quote($accommodation, $arrival, $departure, $query->guests, $query->pets);

        return QuoteResource::fromQuote($slug, $arrival, $departure, $quote);
    }
}
