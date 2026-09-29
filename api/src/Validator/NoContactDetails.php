<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * A public text (description, cancellation terms) must not carry a phone number or an email:
 * the owner's phone is given only to the traveller whose request he accepts, and an address
 * left on a public page is read by spam robots. The listing import already removes them; this
 * closes the same door for what is typed by hand.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class NoContactDetails extends Constraint
{
    public string $message = 'Retirez {{ found }} de ce texte : il est public. Votre téléphone, renseigné dans Mon profil, n\'est donné qu\'au voyageur dont vous acceptez la demande, et les échanges passent par les demandes.';
}
