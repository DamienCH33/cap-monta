<?php

declare(strict_types=1);

namespace App\Validator;

use App\Service\ListingImport\ContactDetector;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class NoContactDetailsValidator extends ConstraintValidator
{
    public function __construct(private readonly ContactDetector $contacts)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof NoContactDetails) {
            throw new UnexpectedTypeException($constraint, NoContactDetails::class);
        }

        if (!\is_string($value) || '' === $value) {
            return;
        }

        $found = $this->contacts->find($value);
        if ([] === $found) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ found }}', '« '.implode(' », « ', $found).' »')
            ->addViolation();
    }
}
