<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Resort;
use App\Repository\StayAlertRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * « Prévenez-moi » : a guest found nothing free for his dates and leaves his email. When a
 * home frees up (a request expires, an owner unblocks a week, a new listing goes online), the
 * site writes to him once per home.
 *
 * Nothing happens before he clicks the link of the first email (confirmedAt): an address typed
 * by someone else never receives more than that one email, and is purged two days later.
 * The alert dies with its arrival date (PurgePersonalDataCommand).
 */
#[ORM\Entity(repositoryClass: StayAlertRepository::class)]
#[ORM\Index(name: 'stay_alert_arrival_idx', columns: ['arrival'])]
#[ORM\Index(name: 'stay_alert_email_idx', columns: ['email'])]
class StayAlert
{
    /** Alerts kept per address: one family searching a few weeks, not a script. */
    public const int MAX_PER_EMAIL = 10;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 180)]
    private string $email;

    /** The language of the page where the alert was created: the one of its emails. */
    #[ORM\Column(length: 2)]
    private string $locale;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $arrival;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $departure;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $guests;

    /** Null: either resort. */
    #[ORM\Column(nullable: true, enumType: Resort::class)]
    private ?Resort $resort;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $confirmedAt = null;

    /**
     * Homes already announced, or already free when the alert was confirmed (he had seen them
     * in the search): each home is announced once.
     *
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON, options: ['default' => '[]'])]
    private array $notifiedSlugs = [];

    public function __construct(
        string $email,
        string $locale,
        \DateTimeImmutable $arrival,
        \DateTimeImmutable $departure,
        int $guests,
        ?Resort $resort,
        \DateTimeImmutable $createdAt,
    ) {
        if ($departure <= $arrival) {
            throw new \InvalidArgumentException('The departure must come after the arrival.');
        }

        $this->id = Uuid::v7();
        $this->email = $email;
        $this->locale = $locale;
        $this->arrival = $arrival;
        $this->departure = $departure;
        $this->guests = $guests;
        $this->resort = $resort;
        $this->createdAt = $createdAt;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getArrival(): \DateTimeImmutable
    {
        return $this->arrival;
    }

    public function getDeparture(): \DateTimeImmutable
    {
        return $this->departure;
    }

    public function getGuests(): int
    {
        return $this->guests;
    }

    public function getResort(): ?Resort
    {
        return $this->resort;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getConfirmedAt(): ?\DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    public function isConfirmed(): bool
    {
        return null !== $this->confirmedAt;
    }

    /**
     * @return list<string>
     */
    public function getNotifiedSlugs(): array
    {
        return $this->notifiedSlugs;
    }

    /**
     * @param list<string> $alreadyFree the homes the search showed him: not news
     */
    public function confirm(\DateTimeImmutable $at, array $alreadyFree): void
    {
        if (null !== $this->confirmedAt) {
            return;
        }

        $this->confirmedAt = $at;
        $this->markNotified($alreadyFree);
    }

    /**
     * @param list<string> $slugs
     */
    public function markNotified(array $slugs): void
    {
        $this->notifiedSlugs = array_values(array_unique([...$this->notifiedSlugs, ...$slugs]));
    }

    public function nights(): int
    {
        return (int) $this->arrival->diff($this->departure)->days;
    }
}
