<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AccommodationStatus;
use App\Enum\AccommodationType;
use App\Enum\Resort;
use App\Exception\InvalidStatusTransitionException;
use App\Exception\PublicationRefusedException;
use App\Repository\AccommodationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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

    #[ORM\Column(enumType: AccommodationStatus::class)]
    private AccommodationStatus $status = AccommodationStatus::Draft;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $amenities = [];

    #[ORM\Column(type: Types::TEXT)]
    private string $description;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $owner;

    /**
     * Ordered: the first one is the cover. Removing a photo from this collection deletes it.
     *
     * @var Collection<int, Photo>
     */
    #[ORM\OneToMany(targetEntity: Photo::class, mappedBy: 'accommodation', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $photos;

    public function __construct(
        string $slug,
        Resort $resort,
        AccommodationType $type,
        int $capacity,
        int $bedrooms,
        string $description,
        User $owner,
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
        $this->owner = $owner;
        $this->photos = new ArrayCollection();
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

    public function getOwner(): User
    {
        return $this->owner;
    }

    /**
     * @return list<Photo> ordered by position, the cover first
     */
    public function getPhotos(): array
    {
        $photos = $this->photos->toArray();
        usort($photos, static fn (Photo $a, Photo $b): int => $a->getPosition() <=> $b->getPosition());

        return $photos;
    }

    public function countPhotos(): int
    {
        return $this->photos->count();
    }

    /** The new photo goes last: the cover does not change behind the owner's back. */
    public function addPhoto(Photo $photo): void
    {
        $photo->moveTo($this->photos->count());
        $this->photos->add($photo);
    }

    public function removePhoto(Photo $photo): void
    {
        $this->photos->removeElement($photo);
        $this->renumberPhotos($this->getPhotos());
    }

    public function findPhoto(string $id): ?Photo
    {
        foreach ($this->photos as $photo) {
            if ($photo->getId()->toRfc4122() === $id) {
                return $photo;
            }
        }

        return null;
    }

    /**
     * Puts the photos in the given order: the first id becomes the cover.
     *
     * @param list<string> $ids every photo of this accommodation, each exactly once
     *
     * @throws \InvalidArgumentException when the list does not match the photos
     */
    public function reorderPhotos(array $ids): void
    {
        if (\count($ids) !== $this->photos->count() || \count(array_unique($ids)) !== \count($ids)) {
            throw new \InvalidArgumentException('The list must contain every photo exactly once.');
        }

        $ordered = [];

        foreach ($ids as $id) {
            $ordered[] = $this->findPhoto($id) ?? throw new \InvalidArgumentException(sprintf('Unknown photo "%s".', $id));
        }

        $this->renumberPhotos($ordered);
    }

    /**
     * @param list<Photo> $photos
     */
    private function renumberPhotos(array $photos): void
    {
        foreach ($photos as $position => $photo) {
            $photo->moveTo($position);
        }
    }

    public function getStatus(): AccommodationStatus
    {
        return $this->status;
    }

    public function isPublished(): bool
    {
        return AccommodationStatus::Published === $this->status;
    }

    public const int MIN_DESCRIPTION_LENGTH = 50;

    /**
     * What is still missing for this accommodation to be, or stay, public.
     * One definition, used when editing a published accommodation and when publishing.
     *
     * @return list<'description'|'district'>
     */
    public function missingForPublication(): array
    {
        $missing = [];

        if (mb_strlen(trim($this->description)) < self::MIN_DESCRIPTION_LENGTH) {
            $missing[] = 'description';
        }

        // Every CHM accommodation sits in a district; Euronat's are not referenced yet.
        if (Resort::Chm === $this->resort && null === $this->district) {
            $missing[] = 'district';
        }

        return $missing;
    }

    public function publish(): void
    {
        // Draft or archived -> published. Publishing twice changes nothing (double click).
        if (AccommodationStatus::Published === $this->status) {
            return;
        }

        if (!$this->owner->isVerified()) {
            throw PublicationRefusedException::unverifiedOwner();
        }

        $missing = $this->missingForPublication();

        if ([] !== $missing) {
            throw PublicationRefusedException::incomplete($missing);
        }

        $this->status = AccommodationStatus::Published;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function archive(): void
    {
        // Published -> archived: off the site, not deleted. Archiving twice changes nothing.
        if (AccommodationStatus::Archived === $this->status) {
            return;
        }

        if (AccommodationStatus::Draft === $this->status) {
            throw new InvalidStatusTransitionException('A draft has never been public: there is nothing to archive.');
        }

        $this->status = AccommodationStatus::Archived;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
