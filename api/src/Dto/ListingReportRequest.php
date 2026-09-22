<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\ListingReport;
use App\Enum\ReportReason;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ListingReportRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Choisissez la raison du signalement.')]
        #[Assert\Choice(callback: [self::class, 'reasons'], message: 'Choisissez la raison du signalement.')]
        public string $reason = '',
        #[Assert\Length(max: ListingReport::MESSAGE_MAX_LENGTH, maxMessage: 'Le message fait {{ limit }} caractères au plus.')]
        public ?string $message = null,
        #[Assert\Email(message: 'Cette adresse email ne semble pas valide.')]
        #[Assert\Length(max: 180)]
        public ?string $email = null,
    ) {
    }

    /**
     * @return list<string>
     */
    public static function reasons(): array
    {
        return array_map(static fn (ReportReason $reason): string => $reason->value, ReportReason::cases());
    }
}
