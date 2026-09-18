<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class RegistrationRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email]
        #[Assert\Length(max: 180)]
        public string $email = '',

        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 80)]
        public string $displayName = '',

        #[Assert\NotBlank(message: 'Choisissez un mot de passe.')]
        #[Assert\Length(
            min: 10,
            max: 4096,
            minMessage: 'Votre mot de passe doit faire au moins {{ limit }} caractères.',
        )]
        #[Assert\NotCompromisedPassword(skipOnError: true)]
        public string $password = '',
    ) {
    }
}
