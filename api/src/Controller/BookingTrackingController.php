<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\BookingRequest;
use App\Repository\BookingRequestRepository;
use App\Service\Booking\BookingAnswerRefused;
use App\Service\Booking\BookingDesk;
use App\Service\Booking\BookingRequestView;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The guest's side, without an account: the private link sent by email is the key.
 * Anyone holding the link can read and cancel the request — like a booking reference.
 */
#[Route('/api/booking-requests/track/{token}', requirements: ['token' => '[0-9a-f]{48}'])]
final class BookingTrackingController
{
    public function __construct(
        private readonly BookingRequestRepository $requests,
        private readonly BookingDesk $desk,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('', name: 'api_booking_tracking', methods: ['GET'])]
    public function show(string $token): JsonResponse
    {
        return new JsonResponse($this->view($this->find($token)));
    }

    #[Route('/cancel', name: 'api_booking_tracking_cancel', methods: ['POST'])]
    public function cancel(string $token): JsonResponse
    {
        $request = $this->find($token);

        try {
            $this->desk->cancelByGuest($request);
        } catch (BookingAnswerRefused $refused) {
            return new JsonResponse([
                'title' => 'An error occurred',
                'detail' => $refused->getMessage(),
                'status' => $refused->status,
                'violations' => [['propertyPath' => $refused->field, 'message' => $refused->getMessage(), 'title' => $refused->getMessage()]],
            ], $refused->status);
        }

        return new JsonResponse($this->view($request));
    }

    private function find(string $token): BookingRequest
    {
        return $this->requests->findOneByTrackingToken($token) ?? throw new NotFoundHttpException();
    }

    /**
     * @return array<string, mixed>
     */
    private function view(BookingRequest $request): array
    {
        return BookingRequestView::forGuest($request, $this->clock->now()->setTime(0, 0));
    }
}
