<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class RegistrationRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Indiquez votre adresse email.')]
        #[Assert\Email(message: 'Cette adresse email ne semble pas valide.')]
        #[Assert\Length(max: 180)]
        public string $email = '',

        #[Assert\NotBlank(message: 'Indiquez le nom à afficher sur votre annonce.')]
        #[Assert\Length(
            min: 2,
            max: 80,
            minMessage: 'Ce nom est trop court.',
            maxMessage: 'Ce nom ne peut pas dépasser {{ limit }} caractères.',
        )]
        public string $displayName = '',

        #[Assert\NotBlank(message: 'Choisissez un mot de passe.')]
        #[Assert\Length(
            min: 10,
            max: 4096,
            minMessage: 'Votre mot de passe doit faire au moins {{ limit }} caractères.',
        )]
        #[Assert\NotCompromisedPassword(
            message: 'Ce mot de passe apparaît dans des fuites de données connues. Choisissez-en un autre.',
            skipOnError: true,
        )]
        public string $password = '',
    ) {
    }
}
