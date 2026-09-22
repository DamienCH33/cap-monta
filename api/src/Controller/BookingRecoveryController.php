<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\BookingRequestRepository;
use App\Service\Booking\BookingMailer;
use App\Service\Http\FloodGuard;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * "Retrouver mes demandes": the guest types his email, the tracking links go to that mailbox.
 *
 * Always the same answer (202), whether the address has requests or not: the page must not
 * reveal who asked for what — closed community, naturist resorts. Same rule as the
 * forgotten password.
 */
final class BookingRecoveryController
{
    public function __construct(
        private readonly BookingRequestRepository $requests,
        private readonly BookingMailer $mailer,
        private readonly ValidatorInterface $validator,
        private readonly FloodGuard $floodGuard,
        private readonly ClockInterface $clock,
        #[Target('booking_recoveries')]
        private readonly RateLimiterFactoryInterface $bookingRecoveriesLimiter,
    ) {
    }

    #[Route('/api/booking-requests/recover', name: 'api_booking_recovery', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $this->floodGuard->check($this->bookingRecoveriesLimiter);

        $email = '' === $request->getContent() ? null : ($request->toArray()['email'] ?? null);

        if (!\is_string($email) || '' === trim($email) || \count($this->validator->validate($email, new Email())) > 0) {
            $message = 'Cette adresse email ne semble pas valide.';

            return new JsonResponse([
                'title' => 'An error occurred',
                'detail' => $message,
                'status' => 422,
                'violations' => [['propertyPath' => 'email', 'message' => $message, 'title' => $message]],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $email = mb_strtolower(trim($email));
        $this->mailer->recovery($email, $this->requests->findOngoingForGuest($email, $this->clock->now()->setTime(0, 0)));

        return new JsonResponse(null, Response::HTTP_ACCEPTED);
    }
}
