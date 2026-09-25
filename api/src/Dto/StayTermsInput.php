<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\StayTerms;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The stay conditions as sent by the owner's form, amounts in cents. Always sent whole:
 * a field left empty removes the condition.
 */
final class StayTermsInput
{
    private const TIME = '/^([01]\d|2[0-3]):[0-5]\d$/';

    #[Assert\Regex(pattern: self::TIME, message: "Heure d'arrivée au format 16:00.")]
    public ?string $checkInFrom = null;

    #[Assert\Regex(pattern: self::TIME, message: 'Heure de départ au format 10:00.')]
    public ?string $checkOutBefore = null;

    #[Assert\Range(notInRangeMessage: "L'acompte doit être compris entre {{ min }} et {{ max }} %.", min: 0, max: 100)]
    public ?int $depositPercent = null;

    #[Assert\Range(notInRangeMessage: 'La caution doit être comprise entre 0 et 10 000 €.', min: 0, max: 1_000_000)]
    public ?int $securityDeposit = null;

    #[Assert\Length(max: 600, maxMessage: "Les conditions d'annulation ne peuvent pas dépasser {{ limit }} caractères.")]
    public ?string $cancellationPolicy = null;

    #[Assert\Range(notInRangeMessage: 'Le ménage doit être compris entre 0 et 500 €.', min: 0, max: 50_000)]
    public ?int $cleaningFee = null;

    #[Assert\Range(notInRangeMessage: 'Le linge doit être compris entre 0 et 100 € par personne.', min: 0, max: 10_000)]
    public ?int $linenFee = null;

    #[Assert\Range(notInRangeMessage: 'La taxe de séjour doit être comprise entre 0 et 10 € par nuit.', min: 0, max: 1_000)]
    public ?int $touristTax = null;

    #[Assert\Range(notInRangeMessage: 'La redevance doit être comprise entre 0 et 50 € par nuit.', min: 0, max: 5_000)]
    public ?int $resortFee = null;

    public function toTerms(): StayTerms
    {
        $cancellation = null === $this->cancellationPolicy ? null : trim($this->cancellationPolicy);

        return new StayTerms(
            $this->checkInFrom ?: null,
            $this->checkOutBefore ?: null,
            $this->depositPercent,
            $this->securityDeposit,
            '' === $cancellation ? null : $cancellation,
            $this->cleaningFee,
            $this->linenFee,
            $this->touristTax,
            $this->resortFee,
        );
    }
}
