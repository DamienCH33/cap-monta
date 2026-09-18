<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class ResetPasswordRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public string $jeton = '',

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
