<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\BookingRequestStatus;
use App\Repository\BookingRequestRepository;
use App\ValueObject\DateRange;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: BookingRequestRepository::class)]
#[ORM\Index(name: 'idx_booking_request_pending', columns: ['status', 'expires_at'])]
class BookingRequest
{
    /**
     * Delay left to the owner before the request expires by itself.
     */
    public const RESPONSE_DELAY = '+48 hours';

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Accommodation $accommodation;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $startDate;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $endDate;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $adults;

    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 0])]
    private int $infants = 0;

    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 0])]
    private int $pets = 0;

    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 0])]
    private int $children = 0;

    #[ORM\Column(length: 255)]
    private string $guestName;

    #[ORM\Column(length: 255)]
    private string $guestEmail;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $guestPhone = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $message = null;

    #[ORM\Column(enumType: BookingRequestStatus::class)]
    private BookingRequestStatus $status = BookingRequestStatus::Pending;

    /**
     * Price in cents, frozen when the request is made. Null when the owner published no rate.
     */
    #[ORM\Column(nullable: true)]
    private ?int $estimatedPrice = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    /**
     * The guest's private key to follow or cancel the request, sent by email. Random, unlike
     * the id (a UUID v7 starts with a timestamp): knowing one request says nothing of another.
     */
    #[ORM\Column(length: 48, unique: true)]
    private string $trackingToken;

    /** Price agreed on acceptance, in cents: the estimate, or the owner's when it was "à convenir". */
    #[ORM\Column(nullable: true)]
    private ?int $agreedPrice = null;

    /** What the owner wrote when answering, sent to the guest. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $ownerMessage = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $respondedAt = null;

    /**
     * The stay ignores a preference of the owner (arrival day). Informative only: the owner
     * decides. A hard rule, like the minimum number of nights, refuses the request instead.
     */
    #[ORM\Column(options: ['default' => false])]
    private bool $outsideRules = false;

    public function __construct(
        Accommodation $accommodation,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
        int $adults,
        string $guestName,
        string $guestEmail,
    ) {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
        $this->expiresAt = $this->createdAt->modify(self::RESPONSE_DELAY);
        $this->accommodation = $accommodation;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->adults = $adults;
        $this->guestName = $guestName;
        $this->guestEmail = $guestEmail;
        $this->trackingToken = bin2hex(random_bytes(24));
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getAccommodation(): Accommodation
    {
        return $this->accommodation;
    }

    public function getStartDate(): \DateTimeImmutable
    {
        return $this->startDate;
    }

    public function getEndDate(): \DateTimeImmutable
    {
        return $this->endDate;
    }

    public function getAdults(): int
    {
        return $this->adults;
    }

    public function getChildren(): int
    {
        return $this->children;
    }

    public function setChildren(int $children): static
    {
        $this->children = $children;

        return $this;
    }

    public function getInfants(): int
    {
        return $this->infants;
    }

    public function setInfants(int $infants): static
    {
        $this->infants = $infants;

        return $this;
    }

    public function getPets(): int
    {
        return $this->pets;
    }

    public function setPets(int $pets): static
    {
        $this->pets = $pets;

        return $this;
    }

    public function getGuests(): int
    {
        return $this->adults + $this->children;
    }

    public function getGuestName(): string
    {
        return $this->guestName;
    }

    public function getGuestEmail(): string
    {
        return $this->guestEmail;
    }

    public function getGuestPhone(): ?string
    {
        return $this->guestPhone;
    }

    public function setGuestPhone(?string $guestPhone): static
    {
        $this->guestPhone = $guestPhone;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getStatus(): BookingRequestStatus
    {
        return $this->status;
    }

    /**
     * Read by the workflow's marking store. The status only changes through the
     * "booking_request" state machine: see BookingDesk.
     */
    public function getMarking(): string
    {
        return $this->status->value;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function setMarking(string $marking, array $context = []): void
    {
        $this->status = BookingRequestStatus::from($marking);
    }

    public function isPending(): bool
    {
        return BookingRequestStatus::Pending === $this->status;
    }

    public function isAccepted(): bool
    {
        return BookingRequestStatus::Accepted === $this->status;
    }

    public function getTrackingToken(): string
    {
        return $this->trackingToken;
    }

    public function getAgreedPrice(): ?int
    {
        return $this->agreedPrice;
    }

    /** What the guest will pay: agreed if answered, estimated otherwise; null when "à convenir". */
    public function price(): ?int
    {
        return $this->agreedPrice ?? $this->estimatedPrice;
    }

    public function getOwnerMessage(): ?string
    {
        return $this->ownerMessage;
    }

    public function getRespondedAt(): ?\DateTimeImmutable
    {
        return $this->respondedAt;
    }

    /** Records the owner's answer. The status itself is changed by the workflow. */
    public function recordAnswer(\DateTimeImmutable $at, ?string $message, ?int $agreedPrice = null): void
    {
        $this->respondedAt = $at;
        $this->ownerMessage = null === $message || '' === trim($message) ? null : trim($message);

        if (null !== $agreedPrice) {
            $this->agreedPrice = $agreedPrice;
        }
    }

    public function isOutsideRules(): bool
    {
        return $this->outsideRules;
    }

    public function markOutsideRules(bool $outside): static
    {
        $this->outsideRules = $outside;

        return $this;
    }

    /** The stay has begun or is over: nothing can be accepted or cancelled any more. */
    public function hasStarted(\DateTimeImmutable $today): bool
    {
        return $this->startDate <= $today;
    }

    public function getEstimatedPrice(): ?int
    {
        return $this->estimatedPrice;
    }

    public function setEstimatedPrice(?int $estimatedPrice): static
    {
        $this->estimatedPrice = $estimatedPrice;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function range(): DateRange
    {
        return new DateRange($this->startDate, $this->endDate);
    }

    public function nights(): int
    {
        return $this->range()->nights();
    }
}
