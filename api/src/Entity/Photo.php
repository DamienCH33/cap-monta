<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * A photo of an accommodation. Only the resized versions are kept (large + thumbnail,
 * WebP): the original is never stored. The files are named after the id.
 *
 * Position 0 is the cover: the picture shown on search cards.
 */
#[ORM\Entity]
#[ORM\Index(name: 'photo_accommodation_position_idx', columns: ['accommodation_id', 'position'])]
class Photo
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: 'photos')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Accommodation $accommodation;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $position = 0;

    /** Size of the large version, in pixels: lets the front reserve the space before loading. */
    #[ORM\Column(type: Types::SMALLINT)]
    private int $width;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $height;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Accommodation $accommodation, int $width, int $height)
    {
        $this->id = Uuid::v7();
        $this->accommodation = $accommodation;
        $this->width = $width;
        $this->height = $height;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getAccommodation(): Accommodation
    {
        return $this->accommodation;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    /** Called by Accommodation only: it keeps the positions of its photos consecutive. */
    public function moveTo(int $position): void
    {
        $this->position = $position;
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
