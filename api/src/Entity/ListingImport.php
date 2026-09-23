<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ListingImportFailure;
use App\Enum\ListingImportStatus;
use App\Repository\ListingImportRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * One listing an owner pasted for the assistant to read (ADR 027). Kept so that nothing is lost
 * when the provider fails: the text stays, the owner can retry or copy from it. Also the
 * journal of what the assistant costs and gets wrong: tokens, attempts, out-of-list equipment.
 */
#[ORM\Entity(repositoryClass: ListingImportRepository::class)]
#[ORM\Index(name: 'listing_import_owner_hash', columns: ['owner_id', 'text_hash'])]
class ListingImport
{
    public const int MIN_TEXT_LENGTH = 30;
    public const int MAX_TEXT_LENGTH = 12000;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $owner;

    #[ORM\Column(type: Types::TEXT)]
    private string $sourceText;

    /** SHA-256 of the normalized text: the same listing pasted twice is read once. */
    #[ORM\Column(length: 64)]
    private string $textHash;

    #[ORM\Column(enumType: ListingImportStatus::class)]
    private ListingImportStatus $status = ListingImportStatus::Pending;

    #[ORM\Column(enumType: ListingImportFailure::class, nullable: true)]
    private ?ListingImportFailure $failure = null;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $attempts = 0;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $model = null;

    /** @var array<string, mixed>|null ListingExtraction::toArray() plus the contacts found */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $result = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $lastError = null;

    #[ORM\Column(nullable: true)]
    private ?int $inputTokens = null;

    #[ORM\Column(nullable: true)]
    private ?int $outputTokens = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    public function __construct(User $owner, string $sourceText, \DateTimeImmutable $now)
    {
        $this->id = Uuid::v7();
        $this->owner = $owner;
        $this->sourceText = $sourceText;
        $this->textHash = self::hash($sourceText);
        $this->createdAt = $now;
    }

    /** Case, accents and spacing do not make a different listing. */
    public static function hash(string $text): string
    {
        $normalized = mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($text)));

        return hash('sha256', $normalized);
    }

    public function recordAttempt(?string $model, ?int $inputTokens, ?int $outputTokens): void
    {
        ++$this->attempts;
        $this->model = $model;
        $this->inputTokens = ($this->inputTokens ?? 0) + ($inputTokens ?? 0);
        $this->outputTokens = ($this->outputTokens ?? 0) + ($outputTokens ?? 0);
    }

    /**
     * @param array<string, mixed> $result
     */
    public function succeed(array $result, \DateTimeImmutable $now): void
    {
        $this->status = ListingImportStatus::Done;
        $this->result = $result;
        $this->failure = null;
        $this->finishedAt = $now;
    }

    /** Between two attempts: still pending, the reason is kept for the diagnosis. */
    public function noteFailure(ListingImportFailure $failure, ?string $error): void
    {
        $this->failure = $failure;
        $this->lastError = null === $error ? null : mb_substr($error, 0, 2000);
    }

    public function fail(ListingImportFailure $failure, ?string $error, \DateTimeImmutable $now): void
    {
        $this->noteFailure($failure, $error);
        $this->status = ListingImportStatus::Failed;
        $this->finishedAt = $now;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function getSourceText(): string
    {
        return $this->sourceText;
    }

    public function getStatus(): ListingImportStatus
    {
        return $this->status;
    }

    public function isPending(): bool
    {
        return ListingImportStatus::Pending === $this->status;
    }

    public function getFailure(): ?ListingImportFailure
    {
        return $this->failure;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    /** @return array<string, mixed>|null */
    public function getResult(): ?array
    {
        return $this->result;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function getInputTokens(): ?int
    {
        return $this->inputTokens;
    }

    public function getOutputTokens(): ?int
    {
        return $this->outputTokens;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getFinishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }
}
