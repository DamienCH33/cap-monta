<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\UnavailabilitySource;
use App\Repository\UnavailabilityRepository;
use App\ValueObject\DateRange;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: UnavailabilityRepository::class)]
#[ORM\Index(name: 'idx_unavailability_lookup', columns: ['accommodation_id', 'start_date'])]
class Unavailability
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Accommodation $accommodation;

    /**
     * Arrival day, included.
     */
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $startDate;

    /**
     * Departure day, excluded.
     */
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $endDate;

    #[ORM\Column(enumType: UnavailabilitySource::class)]
    private UnavailabilitySource $source;

    /**
     * UID of the imported iCal event, null outside synchronisation.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $externalUid = null;

    /**
     * The owner's own reminder on a block ("famille", "loué hors site"). Never public.
     */
    #[ORM\Column(length: self::NOTE_MAX_LENGTH, nullable: true)]
    private ?string $note = null;

    public const NOTE_MAX_LENGTH = 200;

    /** The accepted request behind a "booking" period: cancelling it frees these dates. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?BookingRequest $bookingRequest = null;

    public function __construct(
        Accommodation $accommodation,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
        UnavailabilitySource $source,
        ?string $note = null,
        ?BookingRequest $bookingRequest = null,
    ) {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
        $this->accommodation = $accommodation;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->source = $source;
        $this->note = null === $note || '' === trim($note) ? null : trim($note);
        $this->bookingRequest = $bookingRequest;
    }

    public function getBookingRequest(): ?BookingRequest
    {
        return $this->bookingRequest;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
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

    public function range(): DateRange
    {
        return new DateRange($this->startDate, $this->endDate);
    }

    /**
     * Nights covered, [) bounds: 14th to 15th August is 1 night.
     */
    public function nights(): int
    {
        return $this->range()->nights();
    }

    public function getSource(): UnavailabilitySource
    {
        return $this->source;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    /** Only the owner's manual blocks can be removed from his calendar screen. */
    public function isOwnerBlock(): bool
    {
        return UnavailabilitySource::Block === $this->source;
    }

    public function getExternalUid(): ?string
    {
        return $this->externalUid;
    }

    public function setExternalUid(?string $externalUid): static
    {
        $this->externalUid = $externalUid;

        return $this;
    }
}
