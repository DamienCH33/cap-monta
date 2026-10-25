<?php

declare(strict_types=1);

namespace App\Dto;

use App\State\StayQuery;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class StayAlertRequest
{
    public const int MAX_GUESTS = StayQuery::MAX_GUESTS;

    public function __construct(
        #[Assert\NotBlank(message: 'Indiquez votre adresse email.')]
        #[Assert\Email(message: 'Cette adresse email ne semble pas valide.')]
        #[Assert\Length(max: 180)]
        public string $email = '',

        #[Assert\NotBlank(message: 'Choisissez vos dates.')]
        #[Assert\Date(message: 'Choisissez vos dates.')]
        public string $arrival = '',

        #[Assert\NotBlank(message: 'Choisissez vos dates.')]
        #[Assert\Date(message: 'Choisissez vos dates.')]
        public string $departure = '',

        #[Assert\Range(notInRangeMessage: 'Indiquez entre {{ min }} et {{ max }} voyageurs.', min: 1, max: self::MAX_GUESTS)]
        public int $guests = 1,

        #[Assert\Choice(choices: ['chm', 'euronat'], message: 'Domaine inconnu.')]
        public ?string $resort = null,

        #[Assert\IsTrue(message: 'Acceptez de recevoir ces emails pour créer l’alerte.')]
        public bool $consent = false,
    ) {
    }
}
