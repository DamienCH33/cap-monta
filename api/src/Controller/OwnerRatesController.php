<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Accommodation;
use App\Entity\PricePeriod;
use App\Repository\AccommodationRepository;
use App\Repository\PricePeriodRepository;
use App\Security\Voter\AccommodationVoter;
use App\Service\Pricing\RateRules;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * The rates of an accommodation, in its owner's space: periods with a weekly and/or nightly
 * price, a minimum stay (a hard rule: shorter requests are refused) and an optional Saturday
 * arrival preference (a soft one: requests are flagged, never refused).
 *
 * Prices travel in cents, like everywhere in the API; the screen shows euros. Gaps between
 * periods are allowed: a stay without a rate is "à convenir", and the owner sets the price
 * when he accepts the request.
 */
#[Route('/api/owner/accommodations/{slug}/rates')]
final class OwnerRatesController
{
    public const HORIZON = RateRules::HORIZON;
    public const MAX_MINIMUM_NIGHTS = RateRules::MAX_MINIMUM_NIGHTS;

    public function __construct(
        private readonly AccommodationRepository $accommodations,
        private readonly PricePeriodRepository $periods,
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('', name: 'api_owner_rates', methods: ['GET'])]
    public function show(string $slug): JsonResponse
    {
        return new JsonResponse($this->view($this->ownedAccommodation($slug)));
    }

    #[Route('', name: 'api_owner_rates_create', methods: ['POST'])]
    public function create(string $slug, Request $request): JsonResponse
    {
        $accommodation = $this->ownedAccommodation($slug);

        return $this->save($accommodation, null, $request, Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_owner_rates_update', requirements: ['id' => Requirement::UUID], methods: ['PUT'])]
    public function update(string $slug, string $id, Request $request): JsonResponse
    {
        $accommodation = $this->ownedAccommodation($slug);
        $period = $this->ownedPeriod($accommodation, $id);

        if ($period->getEndDate() <= $this->today()) {
            return self::refuse('start', 'Cette période est passée : elle ne se modifie plus.', Response::HTTP_CONFLICT);
        }

        return $this->save($accommodation, $period, $request, Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'api_owner_rates_delete', requirements: ['id' => Requirement::UUID], methods: ['DELETE'])]
    public function delete(string $slug, string $id): JsonResponse
    {
        $accommodation = $this->ownedAccommodation($slug);
        $period = $this->ownedPeriod($accommodation, $id);

        if ($period->getEndDate() <= $this->today()) {
            return self::refuse('start', 'Cette période est passée : elle reste dans l’historique.', Response::HTTP_CONFLICT);
        }

        $this->em->remove($period);
        $this->em->flush();

        return new JsonResponse($this->view($accommodation));
    }

    /**
     * "Reprendre les tarifs de l'an dernier": every period starting in the given year, shifted
     * by 52 weeks so that a Saturday stays a Saturday. Copies that would overlap an existing
     * period, or end in the past, are skipped and counted.
     */
    #[Route('/copy', name: 'api_owner_rates_copy', methods: ['POST'])]
    public function copy(string $slug, Request $request): JsonResponse
    {
        $accommodation = $this->ownedAccommodation($slug);
        $year = ('' === $request->getContent() ? [] : $request->toArray())['fromYear'] ?? null;

        if (!\is_int($year) || $year < 2000 || $year > 2100) {
            return self::refuse('fromYear', 'Choisissez l’année à recopier.');
        }

        $copied = 0;
        $skipped = 0;

        foreach ($this->periods->findAllFor($accommodation) as $source) {
            if ((int) $source->getStartDate()->format('Y') !== $year) {
                continue;
            }

            $start = $source->getStartDate()->modify('+52 weeks');
            $end = $source->getEndDate()->modify('+52 weeks');

            if ($end <= $this->today() || $this->periods->hasOverlap($accommodation, $start, $end)) {
                ++$skipped;

                continue;
            }

            $copy = new PricePeriod($accommodation, $start, $end, $source->getMinimumNights());
            $copy->setWeeklyPrice($source->getWeeklyPrice())
                ->setNightlyPrice($source->getNightlyPrice())
                ->setSaturdayArrival($source->prefersSaturdayArrival());
            $this->em->persist($copy);
            // Flushed one by one: hasOverlap() only sees what is in the database.
            $this->em->flush();
            ++$copied;
        }

        return new JsonResponse(['copied' => $copied, 'skipped' => $skipped, ...$this->view($accommodation)]);
    }

    private function save(Accommodation $accommodation, ?PricePeriod $period, Request $request, int $status): JsonResponse
    {
        $payload = '' === $request->getContent() ? [] : $request->toArray();
        $start = RateRules::date($payload['start'] ?? null);
        $end = RateRules::date($payload['end'] ?? null);
        $weekly = $payload['weeklyPrice'] ?? null;
        $nightly = $payload['nightlyPrice'] ?? null;
        $minimum = $payload['minimumNights'] ?? 1;
        $saturday = $payload['saturdayArrival'] ?? false;
        $today = $this->today();

        $refusal = RateRules::refusal($start, $end, $weekly, $nightly, $minimum, $saturday, $today);

        if (null !== $refusal) {
            return self::refuse(...$refusal);
        }

        \assert(null !== $start && null !== $end);
        \assert(\is_int($minimum) && \is_bool($saturday));
        \assert((null === $weekly || \is_int($weekly)) && (null === $nightly || \is_int($nightly)));

        // The exclusion constraint guarantees it in any case; checking first gives a clear message.
        if ($this->periods->hasOverlap($accommodation, $start, $end, $period)) {
            return self::refuse('start', 'Ces dates chevauchent une autre période : modifiez-la ou choisissez d’autres dates.', Response::HTTP_CONFLICT);
        }

        if (null === $period) {
            $period = new PricePeriod($accommodation, $start, $end, $minimum);
            $this->em->persist($period);
        } else {
            $period->setStartDate($start)->setEndDate($end)->setMinimumNights($minimum);
        }

        $period->setWeeklyPrice($weekly)->setNightlyPrice($nightly)->setSaturdayArrival($saturday);
        $this->em->flush();

        return new JsonResponse($this->view($accommodation), $status);
    }

    /**
     * @return array<string, mixed>
     */
    private function view(Accommodation $accommodation): array
    {
        $today = $this->today();
        $periods = $this->periods->findAllFor($accommodation);

        return [
            'slug' => $accommodation->getSlug(),
            'today' => $today->format('Y-m-d'),
            'periods' => array_map(static fn (PricePeriod $period): array => [
                'id' => $period->getId()->toRfc4122(),
                'start' => $period->getStartDate()->format('Y-m-d'),
                'end' => $period->getEndDate()->format('Y-m-d'),
                'weeklyPrice' => $period->getWeeklyPrice(),
                'nightlyPrice' => $period->getNightlyPrice(),
                'minimumNights' => $period->getMinimumNights(),
                'saturdayArrival' => $period->prefersSaturdayArrival(),
                'past' => $period->getEndDate() <= $today,
            ], $periods),
            'copySuggestion' => $this->copySuggestion($periods),
        ];
    }

    /**
     * The latest year with rates, when the next one has none yet: that is the copy to offer.
     * Not beyond the rates horizon (two years): once next summer is copied, the offer stops
     * instead of proposing the year after, and the year after that.
     *
     * @param list<PricePeriod> $periods
     *
     * @return array{from: int, to: int}|null
     */
    private function copySuggestion(array $periods): ?array
    {
        $years = array_unique(array_map(static fn (PricePeriod $p): int => (int) $p->getStartDate()->format('Y'), $periods));

        if ([] === $years) {
            return null;
        }

        $latest = max($years);

        return $latest + 1 <= (int) $this->today()->format('Y') + 2 ? ['from' => $latest, 'to' => $latest + 1] : null;
    }

    private function ownedAccommodation(string $slug): Accommodation
    {
        $accommodation = $this->accommodations->findOneBy(['slug' => $slug]);

        // 404 and not 403 for someone else's accommodation: the slug must not leak.
        if (null === $accommodation || !$this->security->isGranted(AccommodationVoter::EDIT, $accommodation)) {
            throw new NotFoundHttpException();
        }

        return $accommodation;
    }

    private function ownedPeriod(Accommodation $accommodation, string $id): PricePeriod
    {
        $period = $this->periods->find($id);

        if (null === $period || $period->getAccommodation() !== $accommodation) {
            throw new NotFoundHttpException();
        }

        return $period;
    }

    private function today(): \DateTimeImmutable
    {
        return $this->clock->now()->setTime(0, 0);
    }

    private static function refuse(string $field, string $message, int $status = Response::HTTP_UNPROCESSABLE_ENTITY): JsonResponse
    {
        return new JsonResponse([
            'title' => 'An error occurred',
            'detail' => $message,
            'status' => $status,
            'violations' => [['propertyPath' => $field, 'message' => $message, 'title' => $message]],
        ], $status);
    }
}
