<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\AccommodationType;
use App\Enum\Amenity;
use App\Enum\PetsPolicy;
use App\Enum\Resort;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * What the front sends to create an accommodation. It always starts as a draft:
 * only what makes it meaningful is required here, completeness is checked when
 * publishing. No slug, no status: the server decides both.
 *
 * Nullable properties + NotNull: a missing field gives a French 422, not a type error.
 */
final class CreateAccommodationInput
{
    #[Assert\NotBlank(message: 'Choisissez le domaine.')]
    #[Assert\Choice(callback: 'resorts', message: 'Domaine inconnu.')]
    public ?string $resort = null;

    #[Assert\NotBlank(message: 'Choisissez le type de logement.')]
    #[Assert\Choice(callback: 'types', message: 'Type de logement inconnu.')]
    public ?string $type = null;

    #[Assert\NotNull(message: 'Indiquez la capacité.')]
    #[Assert\Range(notInRangeMessage: 'La capacité doit être comprise entre {{ min }} et {{ max }} personnes.', min: 1, max: 12)]
    public ?int $capacity = null;

    #[Assert\NotNull(message: 'Indiquez le nombre de chambres.')]
    #[Assert\Range(notInRangeMessage: 'Le nombre de chambres doit être compris entre {{ min }} et {{ max }}.', min: 0, max: 6)]
    public ?int $bedrooms = null;

    /** Optional for a draft, required at publication (3b-5). */
    #[Assert\Length(max: 5000, maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères.')]
    public string $description = '';

    #[Assert\Range(notInRangeMessage: 'La surface doit être comprise entre {{ min }} et {{ max }} m².', min: 5, max: 200)]
    public ?int $surface = null;

    /** @var list<string> */
    #[Assert\Count(max: 20, maxMessage: 'Pas plus de {{ limit }} équipements.')]
    #[Assert\All([
        new Assert\NotBlank(message: 'Un équipement ne peut pas être vide.'),
        new Assert\Choice(callback: [Amenity::class, 'values'], message: 'Équipement inconnu : {{ value }}.'),
    ])]
    public array $amenities = [];

    /** District slug, picked from a list by the front. */
    public ?string $district = null;

    #[Assert\Choice(callback: 'petsPolicies', message: 'Réponse inconnue pour les animaux.')]
    public string $petsPolicy = 'on_request';

    /** @return list<string> */
    public static function petsPolicies(): array
    {
        return array_column(PetsPolicy::cases(), 'value');
    }

    /** @return list<string> */
    public static function resorts(): array
    {
        return array_column(Resort::cases(), 'value');
    }

    /** @return list<string> */
    public static function types(): array
    {
        return array_column(AccommodationType::cases(), 'value');
    }
}
