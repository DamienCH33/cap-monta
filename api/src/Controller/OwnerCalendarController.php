<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Accommodation;
use App\Entity\Unavailability;
use App\Enum\UnavailabilitySource;
use App\Repository\AccommodationRepository;
use App\Repository\UnavailabilityRepository;
use App\Security\Voter\AccommodationVoter;
use App\Service\Calendar\PublicCalendar;
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
 * The calendar of an accommodation, in its owner's space.
 *
 * Unlike the public calendar, the owner sees everything: where each period comes from
 * (booking, his own block, import) and the private note he left on his blocks.
 *
 * A block is any range of days, from a single night to a whole season: the owner
 * decides. Rules on the length of a stay (a week minimum, arrival on Saturday) belong
 * to the rates, not here.
 *
 * Every change marks the calendar as checked (the "Calendrier à jour" badge) and drops
 * the public calendar from the cache.
 */
#[Route('/api/owner/accommodations/{slug}/calendar')]
final class OwnerCalendarController
{
    /** How far ahead a block may go: the season after next is already being let. */
    public const HORIZON = '+18 months';

    public function __construct(
        private readonly AccommodationRepository $accommodations,
        private readonly UnavailabilityRepository $unavailabilities,
        private readonly PublicCalendar $publicCalendar,
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('', name: 'api_owner_calendar', methods: ['GET'])]
    public function show(string $slug): JsonResponse
    {
        return new JsonResponse($this->view($this->ownedAccommodation($slug)));
    }

    #[Route('/blocks', name: 'api_owner_calendar_block', methods: ['POST'])]
    public function block(string $slug, Request $request): JsonResponse
    {
        $accommodation = $this->ownedAccommodation($slug);
        $payload = $request->getContent() ? $request->toArray() : [];

        $start = self::date($payload['start'] ?? null);
        $end = self::date($payload['end'] ?? null);
        $note = $payload['note'] ?? null;
        $today = $this->today();

        if (null === $start) {
            return self::refuse('start', 'Choisissez le premier jour indisponible.');
        }

        if (null === $end) {
            return self::refuse('end', 'Choisissez le jour où le logement redevient libre.');
        }

        if ($end <= $start) {
            return self::refuse('end', 'Le jour de fin doit venir après le jour de début.');
        }

        if ($start < $today) {
            return self::refuse('start', 'On ne peut pas bloquer une date passée.');
        }

        if ($end > $today->modify(self::HORIZON)) {
            return self::refuse('end', 'Le calendrier s’arrête à 18 mois : bloquez une période plus proche.');
        }

        if (null !== $note && (!\is_string($note) || mb_strlen($note) > Unavailability::NOTE_MAX_LENGTH)) {
            return self::refuse('note', sprintf('La note fait %d caractères au plus.', Unavailability::NOTE_MAX_LENGTH));
        }

        // The exclusion constraint guarantees it in any case; checking first gives a clear message.
        if ($this->unavailabilities->hasOverlap($accommodation, $start, $end)) {
            return self::refuse('start', 'Ces dates chevauchent une période déjà indisponible.', Response::HTTP_CONFLICT);
        }

        $this->em->persist(new Unavailability($accommodation, $start, $end, UnavailabilitySource::Block, $note));
        $this->changed($accommodation);

        return new JsonResponse($this->view($accommodation), Response::HTTP_CREATED);
    }

    #[Route('/blocks/{id}', name: 'api_owner_calendar_unblock', requirements: ['id' => Requirement::UUID], methods: ['DELETE'])]
    public function unblock(string $slug, string $id): JsonResponse
    {
        $accommodation = $this->ownedAccommodation($slug);
        $unavailability = $this->unavailabilities->find($id);

        if (null === $unavailability || $unavailability->getAccommodation() !== $accommodation) {
            throw new NotFoundHttpException();
        }

        if (!$unavailability->isOwnerBlock()) {
            return self::refuse('id', 'Une réservation ne se retire pas du calendrier : elle s’annule depuis la demande.', Response::HTTP_CONFLICT);
        }

        if ($unavailability->getEndDate() <= $this->today()) {
            return self::refuse('id', 'Cette période est passée : elle reste dans l’historique.', Response::HTTP_CONFLICT);
        }

        $this->em->remove($unavailability);
        $this->changed($accommodation);

        return new JsonResponse($this->view($accommodation));
    }

    /** "Mon calendrier est à jour": nothing changed, but the owner has checked. */
    #[Route('/confirm', name: 'api_owner_calendar_confirm', methods: ['POST'])]
    public function confirm(string $slug): JsonResponse
    {
        $accommodation = $this->ownedAccommodation($slug);
        $accommodation->markCalendarChecked($this->clock->now());
        $this->em->flush();

        return new JsonResponse($this->view($accommodation));
    }

    private function changed(Accommodation $accommodation): void
    {
        $accommodation->markCalendarChecked($this->clock->now());
        $this->em->flush();
        // After the flush: invalidated earlier, a visitor could cache the old calendar again.
        $this->publicCalendar->invalidate($accommodation);
    }

    /**
     * @return array<string, mixed>
     */
    private function view(Accommodation $accommodation): array
    {
        $today = $this->today();
        $to = $today->modify(self::HORIZON);
        $checkedAt = $accommodation->getCalendarCheckedAt();

        return [
            'slug' => $accommodation->getSlug(),
            'from' => $today->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'checkedAt' => $checkedAt?->format(\DateTimeInterface::ATOM),
            'upToDate' => $accommodation->isCalendarUpToDate($this->clock->now()),
            'periods' => array_map(static fn (Unavailability $unavailability): array => [
                'id' => $unavailability->getId()->toRfc4122(),
                'start' => $unavailability->getStartDate()->format('Y-m-d'),
                'end' => $unavailability->getEndDate()->format('Y-m-d'),
                'source' => $unavailability->getSource()->value,
                'note' => $unavailability->getNote(),
                'removable' => $unavailability->isOwnerBlock(),
            ], $this->unavailabilities->findForPeriod($accommodation, $today, $to)),
        ];
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

    private function today(): \DateTimeImmutable
    {
        return $this->clock->now()->setTime(0, 0);
    }

    /** A YYYY-MM-DD date, or null if absent or malformed (2026-02-30 included). */
    private static function date(mixed $value): ?\DateTimeImmutable
    {
        if (!\is_string($value)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return false !== $date && $date->format('Y-m-d') === $value ? $date : null;
    }

    /** Same shape as API Platform's validation errors: the front reads them the same way. */
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
