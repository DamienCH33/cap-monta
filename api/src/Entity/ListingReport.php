<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ReportReason;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * A report sent from a listing page. Kept, not only emailed: as a host that does not
 * moderate beforehand, the site must be able to show it acted once notified (LCEN, DSA).
 */
#[ORM\Entity]
class ListingReport
{
    public const MESSAGE_MAX_LENGTH = 1000;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Accommodation $accommodation;

    #[ORM\Column(enumType: ReportReason::class)]
    private ReportReason $reason;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $message;

    /** Optional: only to answer the person who reported. */
    #[ORM\Column(length: 180, nullable: true)]
    private ?string $reporterEmail;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** Set when the listing was suspended or the report dismissed. */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $handledAt = null;

    public function __construct(Accommodation $accommodation, ReportReason $reason, ?string $message, ?string $reporterEmail, \DateTimeImmutable $createdAt)
    {
        $this->id = Uuid::v7();
        $this->accommodation = $accommodation;
        $this->reason = $reason;
        $this->message = $message;
        $this->reporterEmail = $reporterEmail;
        $this->createdAt = $createdAt;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getAccommodation(): Accommodation
    {
        return $this->accommodation;
    }

    public function getReason(): ReportReason
    {
        return $this->reason;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getReporterEmail(): ?string
    {
        return $this->reporterEmail;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getHandledAt(): ?\DateTimeImmutable
    {
        return $this->handledAt;
    }

    public function markHandled(\DateTimeImmutable $at): void
    {
        $this->handledAt ??= $at;
    }
}
