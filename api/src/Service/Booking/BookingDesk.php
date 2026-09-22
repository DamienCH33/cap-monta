<?php

declare(strict_types=1);

namespace App\Service\Booking;

use App\Entity\BookingRequest;
use App\Entity\Unavailability;
use App\Enum\UnavailabilitySource;
use App\Repository\BookingRequestRepository;
use App\Repository\UnavailabilityRepository;
use App\Service\Calendar\PublicCalendar;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Lock\Exception\LockAcquiringException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Everything that changes the status of a booking request, and nothing else does.
 *
 * Each method checks the rule, applies the workflow transition, keeps the calendar in
 * step (an accepted request blocks its dates, a cancelled one frees them) and warns the
 * people concerned by email — after the database write, never before.
 */
final readonly class BookingDesk
{
    public const MESSAGE_MAX_LENGTH = 1000;

    /** Written in the refusal sent automatically when the dates go to someone else. */
    public const DATES_TAKEN_MESSAGE = 'Ces dates viennent d’être réservées par un autre voyageur.';

    public function __construct(
        private EntityManagerInterface $em,
        #[Target('booking_request')]
        private WorkflowInterface $workflow,
        private UnavailabilityRepository $unavailabilities,
        private BookingRequestRepository $requests,
        private LockFactory $locks,
        private PublicCalendar $publicCalendar,
        private BookingMailer $mailer,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param int|null $price in cents; required when the request had no estimate ("à convenir"),
     *                        and then only: otherwise the rates set it
     *
     * @throws BookingAnswerRefused
     */
    public function accept(BookingRequest $request, ?int $price, ?string $message): void
    {
        self::checkMessage($message);

        if (null !== $price && ($price < 100 || $price > 10_000_000)) {
            throw BookingAnswerRefused::invalidPrice();
        }

        if (null === $price && null === $request->getEstimatedPrice()) {
            throw BookingAnswerRefused::priceRequired();
        }

        // Covered by the rates: the guest was shown this price, it is not negotiated here.
        // The owner sets a price only for a stay "à convenir".
        if (null !== $price && null !== $request->getEstimatedPrice() && $price !== $request->getEstimatedPrice()) {
            throw BookingAnswerRefused::priceFromRates();
        }

        $accommodation = $request->getAccommodation();

        // ADR 004: one request at a time per accommodation. The exclusion constraint is the
        // safety net; the lock turns a race into a clean 409 instead of a failed transaction.
        $lock = $this->locks->createLock('resa:logement:'.$accommodation->getId()->toRfc4122(), 30);
        $others = [];
        $locked = false;

        try {
            $locked = $lock->acquire(true);
        } catch (LockAcquiringException $e) {
            // Redis down: go on without the lock. The exclusion constraint still refuses a
            // double booking; at worst the loser of a race gets an error instead of a 409.
            $this->logger->warning('Booking lock unavailable, accepting without it: {message}', ['message' => $e->getMessage(), 'exception' => $e]);
        }

        try {
            $this->em->refresh($request);
            $this->checkOpen($request, 'accept');

            if ($this->unavailabilities->hasOverlap($accommodation, $request->getStartDate(), $request->getEndDate())) {
                throw BookingAnswerRefused::datesTaken();
            }

            $now = $this->clock->now();
            $request->recordAnswer($now, $message, $price ?? $request->getEstimatedPrice());
            $this->workflow->apply($request, 'accept');

            $this->em->persist(new Unavailability(
                $accommodation,
                $request->getStartDate(),
                $request->getEndDate(),
                UnavailabilitySource::Booking,
                bookingRequest: $request,
            ));

            // The same dates cannot be given twice: the other guests get an answer at once
            // instead of waiting 48 hours for a refusal that is already certain.
            $others = $this->requests->findPendingOverlapping($request);

            foreach ($others as $other) {
                $other->recordAnswer($now, self::DATES_TAKEN_MESSAGE);
                $this->workflow->apply($other, 'decline');
            }

            $accommodation->markCalendarChecked($now);
            $this->em->flush();
        } finally {
            if ($locked) {
                $lock->release();
            }
        }

        $this->publicCalendar->invalidate($accommodation);
        $this->mailer->accepted($request);

        foreach ($others as $other) {
            $this->mailer->declined($other);
        }
    }

    /**
     * @throws BookingAnswerRefused
     */
    public function decline(BookingRequest $request, ?string $message): void
    {
        self::checkMessage($message);
        $this->checkOpen($request, 'decline');

        $request->recordAnswer($this->clock->now(), $message);
        $this->workflow->apply($request, 'decline');
        $this->em->flush();

        $this->mailer->declined($request);
    }

    /**
     * The owner cancels a booking he had accepted: the dates become free again.
     *
     * @throws BookingAnswerRefused
     */
    public function cancelByOwner(BookingRequest $request, ?string $message): void
    {
        self::checkMessage($message);

        if (!$request->isAccepted()) {
            throw BookingAnswerRefused::notCancellable();
        }

        $this->cancel($request);
        $request->recordAnswer($this->clock->now(), $message);
        $this->em->flush();
        $this->publicCalendar->invalidate($request->getAccommodation());

        $this->mailer->cancelledByOwner($request);
    }

    /**
     * The guest withdraws, from his tracking link: a pending request, or an accepted booking.
     *
     * @throws BookingAnswerRefused
     */
    public function cancelByGuest(BookingRequest $request): void
    {
        $wasAccepted = $request->isAccepted();
        $this->cancel($request);
        $this->em->flush();

        if ($wasAccepted) {
            $this->publicCalendar->invalidate($request->getAccommodation());
        }

        $this->mailer->cancelledByGuest($request, $wasAccepted);
    }

    /**
     * No answer before the deadline, or the arrival day has come: the request expires.
     * Safe to call at any time and more than once: it does nothing when it should not.
     */
    public function expire(BookingRequest $request): bool
    {
        $now = $this->clock->now();

        $due = $request->getExpiresAt() <= $now || $request->hasStarted($now->setTime(0, 0));

        if (!$request->isPending() || !$due || !$this->workflow->can($request, 'expire')) {
            return false;
        }

        $this->workflow->apply($request, 'expire');
        $this->em->flush();

        $this->mailer->expired($request);

        return true;
    }

    /**
     * @throws BookingAnswerRefused
     */
    private function cancel(BookingRequest $request): void
    {
        if ($request->hasStarted($this->today())) {
            throw BookingAnswerRefused::alreadyStarted();
        }

        if (!$this->workflow->can($request, 'cancel')) {
            throw BookingAnswerRefused::notWaiting();
        }

        if ($request->isAccepted()) {
            foreach ($this->unavailabilities->findBy(['bookingRequest' => $request]) as $unavailability) {
                $this->em->remove($unavailability);
            }
        }

        $this->workflow->apply($request, 'cancel');
    }

    /**
     * @throws BookingAnswerRefused
     */
    private function checkOpen(BookingRequest $request, string $transition): void
    {
        // Answered after the deadline, before the delayed message did its job: expire it now.
        if ($this->expire($request)) {
            throw BookingAnswerRefused::expired();
        }

        if (!$this->workflow->can($request, $transition)) {
            throw BookingAnswerRefused::notWaiting();
        }

        if ($request->hasStarted($this->today())) {
            throw BookingAnswerRefused::alreadyStarted();
        }
    }

    private function today(): \DateTimeImmutable
    {
        return $this->clock->now()->setTime(0, 0);
    }

    /**
     * @throws BookingAnswerRefused
     */
    private static function checkMessage(?string $message): void
    {
        if (null !== $message && mb_strlen($message) > self::MESSAGE_MAX_LENGTH) {
            throw BookingAnswerRefused::messageTooLong();
        }
    }
}
