<?php

declare(strict_types=1);

namespace App\Service\Booking;

use App\Entity\BookingRequest;
use App\Entity\BookingTrackingLink;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * The key to put in a tracking link. Keys are stored hashed: the one of the creation is known
 * only during the request that created it; any later email gets a new key, recorded hashed
 * beside the first one (BookingTrackingLink). Every link already sent keeps working.
 */
final readonly class BookingTrackingLinks
{
    public function __construct(
        private EntityManagerInterface $em,
        private ClockInterface $clock,
    ) {
    }

    public function keyFor(BookingRequest $request): string
    {
        $fresh = $request->getTrackingToken();

        if (null !== $fresh) {
            return $fresh;
        }

        $token = BookingRequest::newTrackingToken();
        $this->em->persist(new BookingTrackingLink($request, BookingRequest::hashTrackingToken($token), $this->clock->now()));
        $this->em->flush();

        return $token;
    }
}
