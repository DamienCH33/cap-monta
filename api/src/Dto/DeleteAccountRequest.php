<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** The password again: a session left open on a shared computer must not erase an account. */
final class DeleteAccountRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Indiquez votre mot de passe pour confirmer.')]
        public string $password = '',
    ) {
    }
}
