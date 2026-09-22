<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\BookingRequest;
use App\Entity\User;
use App\Repository\BookingRequestRepository;
use App\Repository\UnavailabilityRepository;
use App\Security\Voter\AccommodationVoter;
use App\Service\Booking\BookingAnswerRefused;
use App\Service\Booking\BookingDesk;
use App\Service\Booking\BookingRequestView;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

/**
 * The owner's inbox: the requests on all his accommodations, and his answers.
 *
 * Each answer returns the whole inbox: accepting one request may decline others on the
 * same dates, and the screen must show that at once.
 */
#[Route('/api/owner/booking-requests')]
final class OwnerBookingRequestController
{
    public function __construct(
        private readonly BookingRequestRepository $requests,
        private readonly UnavailabilityRepository $unavailabilities,
        private readonly BookingDesk $desk,
        private readonly Security $security,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('', name: 'api_owner_booking_requests', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return new JsonResponse($this->inbox());
    }

    #[Route('/{id}/accept', name: 'api_owner_booking_request_accept', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function accept(string $id, Request $request): JsonResponse
    {
        $booking = $this->owned($id);
        $payload = self::payload($request);
        $price = $payload['price'] ?? null;

        if (null !== $price && !\is_int($price)) {
            return self::refuse(BookingAnswerRefused::invalidPrice());
        }

        return $this->answer(fn () => $this->desk->accept($booking, $price, self::message($payload)));
    }

    #[Route('/{id}/decline', name: 'api_owner_booking_request_decline', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function decline(string $id, Request $request): JsonResponse
    {
        $booking = $this->owned($id);

        return $this->answer(fn () => $this->desk->decline($booking, self::message(self::payload($request))));
    }

    #[Route('/{id}/cancel', name: 'api_owner_booking_request_cancel', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function cancel(string $id, Request $request): JsonResponse
    {
        $booking = $this->owned($id);

        return $this->answer(fn () => $this->desk->cancelByOwner($booking, self::message(self::payload($request))));
    }

    private function answer(\Closure $action): JsonResponse
    {
        try {
            $action();
        } catch (BookingAnswerRefused $refused) {
            return self::refuse($refused);
        }

        return new JsonResponse($this->inbox());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function inbox(): array
    {
        $owner = $this->security->getUser();
        \assert($owner instanceof User);
        $today = $this->clock->now()->setTime(0, 0);

        return array_map(
            fn (BookingRequest $request): array => BookingRequestView::forOwner(
                $request,
                $today,
                // Dates taken since the request arrived: accepting would fail, better say it now.
                $request->isPending() && $this->unavailabilities->hasOverlap(
                    $request->getAccommodation(),
                    $request->getStartDate(),
                    $request->getEndDate(),
                ),
            ),
            $this->requests->findForOwner($owner),
        );
    }

    private function owned(string $id): BookingRequest
    {
        $request = $this->requests->find(Uuid::fromString($id));

        // 404 and not 403: someone else's request must not be confirmed to exist.
        if (null === $request || !$this->security->isGranted(AccommodationVoter::EDIT, $request->getAccommodation())) {
            throw new NotFoundHttpException();
        }

        return $request;
    }

    /**
     * @return array<string, mixed>
     */
    private static function payload(Request $request): array
    {
        return '' === $request->getContent() ? [] : $request->toArray();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function message(array $payload): ?string
    {
        $message = $payload['message'] ?? null;

        return \is_string($message) ? $message : null;
    }

    private static function refuse(BookingAnswerRefused $refused): JsonResponse
    {
        return new JsonResponse([
            'title' => 'An error occurred',
            'detail' => $refused->getMessage(),
            'status' => $refused->status,
            'violations' => [['propertyPath' => $refused->field, 'message' => $refused->getMessage(), 'title' => $refused->getMessage()]],
        ], $refused->status);
    }
}
