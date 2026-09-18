<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class ForgottenPasswordRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Indiquez votre adresse email.')]
        #[Assert\Email(message: 'Cette adresse email ne semble pas valide.')]
        public string $email = '',
    ) {
    }
}
