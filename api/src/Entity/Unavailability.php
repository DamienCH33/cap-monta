<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\UnavailabilitySource;
use App\Repository\UnavailabilityRepository;
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

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $externalUid = null;

    public function __construct(
        Accommodation $accommodation,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
        UnavailabilitySource $source,
    ) {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
        $this->accommodation = $accommodation;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->source = $source;
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

    public function getSource(): UnavailabilitySource
    {
        return $this->source;
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

    /**
     * Nights covered, [) bounds: 14th to 15th August is 1 night.
     */
    public function nights(): int
    {
        return (int) $this->startDate->diff($this->endDate)->days;
    }
}
