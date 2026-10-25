<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\StayAlertRequest;
use App\Entity\StayAlert;
use App\Enum\Resort;
use App\Http\ApiProblem;
use App\I18n\Translator;
use App\Service\Http\FloodGuard;
use App\Service\StayAlert\StayAlertDesk;
use App\Service\StayAlert\StayAlertLinks;
use App\State\StayQuery;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Prévenez-moi » : a guest leaves his email for dates where nothing is free.
 *
 * Creating an alert always answers 202, whether the address already had one or not: the page
 * must not reveal who searches when (same rule as « Retrouver mes demandes »). The alert's own
 * pages are reached with the signed key of its emails (StayAlertLinks).
 */
final readonly class StayAlertController
{
    public function __construct(
        private StayAlertDesk $desk,
        private StayAlertLinks $links,
        private Translator $translator,
        private FloodGuard $floodGuard,
        private ClockInterface $clock,
        #[Target('stay_alerts')]
        private RateLimiterFactoryInterface $stayAlertsLimiter,
    ) {
    }

    #[Route('/api/stay-alerts', name: 'api_stay_alert_create', methods: ['POST'])]
    public function create(#[MapRequestPayload] StayAlertRequest $payload): JsonResponse
    {
        $this->floodGuard->check($this->stayAlertsLimiter);

        $arrival = new \DateTimeImmutable($payload->arrival);
        $departure = new \DateTimeImmutable($payload->departure);
        $today = $this->clock->now()->setTime(0, 0);

        if ($departure <= $arrival) {
            $message = 'La date de départ doit venir après l’arrivée.';

            return ApiProblem::response(Response::HTTP_UNPROCESSABLE_ENTITY, $message, 'departure');
        }

        if ($arrival <= $today) {
            $message = 'Choisissez une arrivée à partir de demain.';

            return ApiProblem::response(Response::HTTP_UNPROCESSABLE_ENTITY, $message, 'arrival');
        }

        if ($departure > $arrival->modify('+'.StayQuery::MAX_NIGHTS.' days')) {
            $message = 'Ce séjour est trop long pour une alerte.';

            return ApiProblem::response(Response::HTTP_UNPROCESSABLE_ENTITY, $message, 'departure');
        }

        $this->desk->register(
            mb_strtolower(trim($payload->email)),
            $this->translator->locale(),
            $arrival,
            $departure,
            $payload->guests,
            null === $payload->resort ? null : Resort::from($payload->resort),
        );

        return new JsonResponse(null, Response::HTTP_ACCEPTED);
    }

    #[Route('/api/stay-alerts/{key}', name: 'api_stay_alert_show', methods: ['GET'])]
    public function show(string $key): JsonResponse
    {
        return new JsonResponse(self::view($this->alert($key)));
    }

    #[Route('/api/stay-alerts/{key}/confirm', name: 'api_stay_alert_confirm', methods: ['POST'])]
    public function confirm(string $key): JsonResponse
    {
        $alert = $this->alert($key);
        $this->desk->confirm($alert);

        return new JsonResponse(self::view($alert));
    }

    #[Route('/api/stay-alerts/{key}', name: 'api_stay_alert_stop', methods: ['DELETE'])]
    public function stop(string $key): JsonResponse
    {
        $this->desk->stop($this->alert($key));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /** A key the site did not sign, or an alert already stopped or purged: the same 404. */
    private function alert(string $key): StayAlert
    {
        $id = $this->links->idFrom($key);

        return (null === $id ? null : $this->desk->find($id)) ?? throw new NotFoundHttpException();
    }

    /**
     * No email address: the page shows what he asked for, not who he is.
     *
     * @return array{arrival: string, departure: string, guests: int, resort: string|null, confirmed: bool}
     */
    private static function view(StayAlert $alert): array
    {
        return [
            'arrival' => $alert->getArrival()->format('Y-m-d'),
            'departure' => $alert->getDeparture()->format('Y-m-d'),
            'guests' => $alert->getGuests(),
            'resort' => $alert->getResort()?->value,
            'confirmed' => $alert->isConfirmed(),
        ];
    }
}
