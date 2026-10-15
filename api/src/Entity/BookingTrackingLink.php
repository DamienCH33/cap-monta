<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * A tracking link sent after the creation of the request: in the acceptance email, or by
 * "Retrouver mes demandes". The key of the first link is hashed on the request itself; it
 * cannot be written again into a new email, so each later email gets a key of its own, and
 * every link sent keeps working. Only the SHA-256 of the key is stored.
 */
#[ORM\Entity]
class BookingTrackingLink
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private BookingRequest $bookingRequest;

    #[ORM\Column(length: 64, unique: true)]
    private string $tokenHash;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(BookingRequest $bookingRequest, string $tokenHash, \DateTimeImmutable $createdAt)
    {
        $this->id = Uuid::v7();
        $this->bookingRequest = $bookingRequest;
        $this->tokenHash = $tokenHash;
        $this->createdAt = $createdAt;
    }

    public function getBookingRequest(): BookingRequest
    {
        return $this->bookingRequest;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
