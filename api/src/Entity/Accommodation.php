<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AccommodationType;
use App\Enum\Resort;
use App\Repository\AccommodationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: AccommodationRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Accommodation
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(length: 255, unique: true)]
    private string $slug;

    #[ORM\Column(enumType: Resort::class)]
    private Resort $resort;

    #[ORM\Column(enumType: AccommodationType::class)]
    private AccommodationType $type;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?District $district = null;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $capacity;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $maxCapacity;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $bedrooms;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $surface = null;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $amenities = [];

    #[ORM\Column(type: Types::TEXT)]
    private string $description;

    public function __construct(
        string $slug,
        Resort $resort,
        AccommodationType $type,
        int $capacity,
        int $bedrooms,
        string $description,
    ) {
        $now = new \DateTimeImmutable();

        $this->id = Uuid::v7();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->slug = $slug;
        $this->resort = $resort;
        $this->type = $type;
        $this->capacity = $capacity;
        $this->maxCapacity = $capacity;
        $this->bedrooms = $bedrooms;
        $this->description = $description;
    }

    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getResort(): Resort
    {
        return $this->resort;
    }

    public function setResort(Resort $resort): static
    {
        $this->resort = $resort;

        return $this;
    }

    public function getType(): AccommodationType
    {
        return $this->type;
    }

    public function setType(AccommodationType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getDistrict(): ?District
    {
        return $this->district;
    }

    public function setDistrict(?District $district): static
    {
        $this->district = $district;

        return $this;
    }

    public function getCapacity(): int
    {
        return $this->capacity;
    }

    public function setCapacity(int $capacity): static
    {
        $this->capacity = $capacity;

        return $this;
    }

    public function getMaxCapacity(): int
    {
        return $this->maxCapacity;
    }

    public function setMaxCapacity(int $maxCapacity): static
    {
        $this->maxCapacity = $maxCapacity;

        return $this;
    }

    public function getBedrooms(): int
    {
        return $this->bedrooms;
    }

    public function setBedrooms(int $bedrooms): static
    {
        $this->bedrooms = $bedrooms;

        return $this;
    }

    public function getSurface(): ?int
    {
        return $this->surface;
    }

    public function setSurface(?int $surface): static
    {
        $this->surface = $surface;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getAmenities(): array
    {
        return $this->amenities;
    }

    /**
     * @param list<string> $amenities
     */
    public function setAmenities(array $amenities): static
    {
        $this->amenities = $amenities;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }
}
