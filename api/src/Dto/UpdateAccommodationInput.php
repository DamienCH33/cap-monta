<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\AccommodationType;
use App\Enum\Amenity;
use App\Enum\PetsPolicy;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * PATCH body: every field is optional.
 *
 * No default values on purpose: the serializer only sets what the JSON contains,
 * so an absent field stays uninitialized and provided() tells "not sent" apart
 * from "sent as null" (clearing the district or the surface).
 * Not editable: slug (stable URL), status (own operations), owner, resort.
 */
final class UpdateAccommodationInput
{
    #[Assert\Choice(callback: 'types', message: 'Type de logement inconnu.')]
    public string $type;

    #[Assert\Range(notInRangeMessage: 'La capacité doit être comprise entre {{ min }} et {{ max }} personnes.', min: 1, max: 12)]
    public int $capacity;

    #[Assert\Range(notInRangeMessage: 'Le nombre de chambres doit être compris entre {{ min }} et {{ max }}.', min: 0, max: 6)]
    public int $bedrooms;

    #[Assert\Range(notInRangeMessage: 'La surface doit être comprise entre {{ min }} et {{ max }} m².', min: 5, max: 200)]
    public ?int $surface;

    /** @var list<string> */
    #[Assert\Count(max: 20, maxMessage: 'Pas plus de {{ limit }} équipements.')]
    #[Assert\All([
        new Assert\NotBlank(message: 'Un équipement ne peut pas être vide.'),
        new Assert\Choice(callback: [Amenity::class, 'values'], message: 'Équipement inconnu : {{ value }}.'),
    ])]
    public array $amenities;

    #[Assert\Length(max: 5000, maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères.')]
    public string $description;

    /** District slug, or null to remove it. */
    public ?string $district;

    #[Assert\Choice(callback: 'petsPolicies', message: 'Réponse inconnue pour les animaux.')]
    public string $petsPolicy;

    /** Replaces all the conditions at once; null removes them all. */
    #[Assert\Valid]
    public ?StayTermsInput $terms;

    /** @return list<string> */
    public static function petsPolicies(): array
    {
        return array_column(PetsPolicy::cases(), 'value');
    }

    /**
     * The fields actually sent: uninitialized typed properties are left out.
     *
     * @return array<string, mixed>
     */
    public function provided(): array
    {
        return get_object_vars($this);
    }

    /** @return list<string> */
    public static function types(): array
    {
        return array_column(AccommodationType::cases(), 'value');
    }
}
