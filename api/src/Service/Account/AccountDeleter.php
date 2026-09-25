<?php

declare(strict_types=1);

namespace App\Service\Account;

use App\Entity\BookingRequest;
use App\Entity\User;
use App\Enum\BookingRequestStatus;
use App\Repository\AccommodationRepository;
use App\Repository\BookingRequestRepository;
use App\Service\Booking\BookingDesk;
use App\Service\Photo\PhotoStorage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * The owner closes his account (GDPR, right to erasure): his listings, their photos, calendars,
 * rates and requests go with it. What the database cascades, it cascades; the photo files and
 * the guests waiting for an answer are handled here.
 */
final readonly class AccountDeleter
{
    public const string CLOSING_MESSAGE = 'Le propriétaire a fermé son compte Cap Monta : ce logement n’est plus proposé.';

    public function __construct(
        private EntityManagerInterface $em,
        private AccommodationRepository $accommodations,
        private BookingRequestRepository $requests,
        private BookingDesk $desk,
        private PhotoStorage $photos,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws AccountDeletionRefused when guests are expected: they must be told first
     */
    public function delete(User $owner): void
    {
        $today = $this->clock->now()->setTime(0, 0);
        $mine = $this->requests->findForOwner($owner);

        $upcoming = array_filter(
            $mine,
            static fn (BookingRequest $request): bool => BookingRequestStatus::Accepted === $request->getStatus()
                && $request->getEndDate() > $today,
        );

        if ([] !== $upcoming) {
            throw new AccountDeletionRefused(sprintf('%d séjour(s) accepté(s) sont encore à venir. Annulez-les depuis « Demandes » (le voyageur sera prévenu), puis revenez ici.', count($upcoming)));
        }

        // Guests still waiting get a real answer, not a dead link.
        foreach ($mine as $request) {
            if ($request->isPending()) {
                $this->desk->decline($request, self::CLOSING_MESSAGE);
            }
        }

        foreach ($this->accommodations->findByOwner($owner) as $accommodation) {
            foreach ($accommodation->getPhotos() as $photo) {
                $this->photos->delete($photo);
            }

            $this->em->remove($accommodation);
        }

        $this->em->remove($owner);
        $this->em->flush();
    }
}
