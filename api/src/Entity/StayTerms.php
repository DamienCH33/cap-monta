<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * The owner's conditions for a stay, shown on the listing: arrival and departure times,
 * deposit, security deposit, cancellation, and the fees paid on top of the rent.
 *
 * Every field is optional: an owner who says nothing is not given invented conditions.
 * Amounts in cents. Cap Monta never collects any of them: they are paid to the owner (or to
 * the resort for its fee), and the quote only adds them up so that the traveller is not
 * surprised on arrival.
 */
#[ORM\Embeddable]
final class StayTerms
{
    public function __construct(
        /** "16:00": earliest arrival time. */
        #[ORM\Column(length: 5, nullable: true)]
        private ?string $checkInFrom = null,
        /** "10:00": latest departure time. */
        #[ORM\Column(length: 5, nullable: true)]
        private ?string $checkOutBefore = null,
        /** Share of the rent asked when the owner accepts, 0 to 100. */
        #[ORM\Column(type: Types::SMALLINT, nullable: true)]
        private ?int $depositPercent = null,
        /** Refundable security deposit, cents. */
        #[ORM\Column(nullable: true)]
        private ?int $securityDeposit = null,
        /** In the owner's words: nothing forces a standard policy between private people. */
        #[ORM\Column(type: Types::TEXT, nullable: true)]
        private ?string $cancellationPolicy = null,
        /** End-of-stay cleaning, optional, per stay, cents. */
        #[ORM\Column(nullable: true)]
        private ?int $cleaningFee = null,
        /** Bed linen, optional, per person, cents. */
        #[ORM\Column(nullable: true)]
        private ?int $linenFee = null,
        /** Tourist tax per adult and per night, cents (children are exempt). */
        #[ORM\Column(nullable: true)]
        private ?int $touristTax = null,
        /** Resort fee ("redevance du domaine") per person and per night, cents. */
        #[ORM\Column(nullable: true)]
        private ?int $resortFee = null,
    ) {
    }

    public function getCheckInFrom(): ?string
    {
        return $this->checkInFrom;
    }

    public function getCheckOutBefore(): ?string
    {
        return $this->checkOutBefore;
    }

    public function getDepositPercent(): ?int
    {
        return $this->depositPercent;
    }

    public function getSecurityDeposit(): ?int
    {
        return $this->securityDeposit;
    }

    public function getCancellationPolicy(): ?string
    {
        return $this->cancellationPolicy;
    }

    public function getCleaningFee(): ?int
    {
        return $this->cleaningFee;
    }

    public function getLinenFee(): ?int
    {
        return $this->linenFee;
    }

    public function getTouristTax(): ?int
    {
        return $this->touristTax;
    }

    public function getResortFee(): ?int
    {
        return $this->resortFee;
    }

    /**
     * The same shape in the public and the owner's API.
     *
     * @return array{checkInFrom: ?string, checkOutBefore: ?string, depositPercent: ?int, securityDeposit: ?int, cancellationPolicy: ?string, cleaningFee: ?int, linenFee: ?int, touristTax: ?int, resortFee: ?int}
     */
    public function toArray(): array
    {
        return [
            'checkInFrom' => $this->checkInFrom,
            'checkOutBefore' => $this->checkOutBefore,
            'depositPercent' => $this->depositPercent,
            'securityDeposit' => $this->securityDeposit,
            'cancellationPolicy' => $this->cancellationPolicy,
            'cleaningFee' => $this->cleaningFee,
            'linenFee' => $this->linenFee,
            'touristTax' => $this->touristTax,
            'resortFee' => $this->resortFee,
        ];
    }
}
