<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PricePeriodRepository;
use App\ValueObject\DateRange;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: PricePeriodRepository::class)]
class PricePeriod
{
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

    /**
     * Weekly price in cents. Null when the owner did not publish one.
     */
    #[ORM\Column(nullable: true)]
    private ?int $weeklyPrice = null;

    /**
     * Nightly price in cents. Null when the owner did not publish one.
     */
    #[ORM\Column(nullable: true)]
    private ?int $nightlyPrice = null;

    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 1])]
    private int $minimumNights = 1;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        Accommodation $accommodation,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
        int $minimumNights = 1,
    ) {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
        $this->accommodation = $accommodation;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->minimumNights = $minimumNights;
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

    public function setStartDate(\DateTimeImmutable $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): \DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(\DateTimeImmutable $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function range(): DateRange
    {
        return new DateRange($this->startDate, $this->endDate);
    }

    public function getWeeklyPrice(): ?int
    {
        return $this->weeklyPrice;
    }

    public function setWeeklyPrice(?int $weeklyPrice): static
    {
        $this->weeklyPrice = $weeklyPrice;

        return $this;
    }

    public function getNightlyPrice(): ?int
    {
        return $this->nightlyPrice;
    }

    public function setNightlyPrice(?int $nightlyPrice): static
    {
        $this->nightlyPrice = $nightlyPrice;

        return $this;
    }

    public function getMinimumNights(): int
    {
        return $this->minimumNights;
    }

    public function setMinimumNights(int $minimumNights): static
    {
        $this->minimumNights = $minimumNights;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function hasPrice(): bool
    {
        return null !== $this->weeklyPrice || null !== $this->nightlyPrice;
    }
}
