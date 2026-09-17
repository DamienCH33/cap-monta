<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\DistrictArea;
use App\Enum\Resort;
use App\Repository\DistrictRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * A district ("village") of a resort. The reference list is the official site plan.
 */
#[ORM\Entity(repositoryClass: DistrictRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_district_slug', columns: ['slug'])]
#[ORM\UniqueConstraint(name: 'uniq_district_resort_name', columns: ['resort', 'name'])]
class District
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 64)]
    private string $slug;

    #[ORM\Column(length: 100)]
    private string $name;

    #[ORM\Column(enumType: Resort::class)]
    private Resort $resort;

    /** Null until the position has been checked on the plan. */
    #[ORM\Column(nullable: true, enumType: DistrictArea::class)]
    private ?DistrictArea $area = null;

    /** Hand-written text, published only once verified. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $intro = null;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $highlights = [];

    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 0])]
    private int $position = 0;

    public function __construct(string $slug, string $name, Resort $resort)
    {
        $this->id = Uuid::v7();
        $this->slug = $slug;
        $this->name = $name;
        $this->resort = $resort;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getResort(): Resort
    {
        return $this->resort;
    }

    public function getArea(): ?DistrictArea
    {
        return $this->area;
    }

    public function setArea(?DistrictArea $area): static
    {
        $this->area = $area;

        return $this;
    }

    public function getIntro(): ?string
    {
        return $this->intro;
    }

    public function setIntro(?string $intro): static
    {
        $this->intro = $intro;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getHighlights(): array
    {
        return $this->highlights;
    }

    /**
     * @param list<string> $highlights
     */
    public function setHighlights(array $highlights): static
    {
        $this->highlights = $highlights;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }
}
