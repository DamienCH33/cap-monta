<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * What the owner may change on his profile. The email is not here: changing it would need a
 * new verification, and it is the login.
 */
final class UpdateOwnerProfileRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Indiquez le nom à afficher sur votre annonce.')]
        #[Assert\Length(
            min: 2,
            max: 80,
            minMessage: 'Ce nom est trop court.',
            maxMessage: 'Ce nom ne peut pas dépasser {{ limit }} caractères.',
        )]
        public string $displayName = '',

        /** Given to the guest once a request is accepted, never before. */
        #[Assert\Regex(
            pattern: '/^(?:\+33|0)\s*[1-9](?:[\s.\-]*\d{2}){4}$/',
            message: 'Numéro de téléphone français attendu, par exemple 06 12 34 56 78.',
        )]
        public ?string $phone = null,
    ) {
    }
}
