<?php

declare(strict_types=1);

namespace App\Service\Booking;

use App\Entity\BookingRequest;

/**
 * The JSON shapes of a booking request outside API Platform: the owner's inbox and the
 * guest's tracking page. One place decides who sees what — the contact details above all.
 */
final class BookingRequestView
{
    /**
     * @return array<string, mixed>
     */
    public static function common(BookingRequest $request, \DateTimeImmutable $today): array
    {
        $accommodation = $request->getAccommodation();

        return [
            'status' => $request->getStatus()->value,
            'accommodation' => [
                'slug' => $accommodation->getSlug(),
                'title' => $accommodation->title(),
            ],
            'start' => $request->getStartDate()->format('Y-m-d'),
            'end' => $request->getEndDate()->format('Y-m-d'),
            'nights' => $request->nights(),
            'adults' => $request->getAdults(),
            'children' => $request->getChildren(),
            'infants' => $request->getInfants(),
            'pets' => $request->getPets(),
            'estimatedPrice' => $request->getEstimatedPrice(),
            'agreedPrice' => $request->getAgreedPrice(),
            'ownerMessage' => $request->getOwnerMessage(),
            'createdAt' => $request->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'expiresAt' => $request->getExpiresAt()->format(\DateTimeInterface::ATOM),
            'respondedAt' => $request->getRespondedAt()?->format(\DateTimeInterface::ATOM),
            'started' => $request->hasStarted($today),
        ];
    }

    /**
     * The owner sees the guest's name and message; email and phone only once he has accepted.
     *
     * @return array<string, mixed>
     */
    public static function forOwner(BookingRequest $request, \DateTimeImmutable $today, bool $conflict): array
    {
        return [
            'id' => $request->getId()->toRfc4122(),
            ...self::common($request, $today),
            'guestName' => $request->getGuestName(),
            'message' => $request->getMessage(),
            'outsideRules' => $request->isOutsideRules(),
            'conflict' => $conflict,
            'contact' => $request->isAccepted() ? [
                'email' => $request->getGuestEmail(),
                'phone' => $request->getGuestPhone(),
            ] : null,
        ];
    }

    /**
     * The guest sees his own request, and the owner's contact once accepted.
     *
     * @return array<string, mixed>
     */
    public static function forGuest(BookingRequest $request, \DateTimeImmutable $today): array
    {
        $owner = $request->getAccommodation()->getOwner();

        return [
            ...self::common($request, $today),
            'guestName' => $request->getGuestName(),
            'cancellable' => ($request->isPending() || $request->isAccepted()) && !$request->hasStarted($today),
            'ownerContact' => $request->isAccepted() ? [
                'name' => $owner->getDisplayName(),
                'email' => $owner->getEmail(),
                'phone' => $owner->getPhone(),
            ] : null,
        ];
    }
}
