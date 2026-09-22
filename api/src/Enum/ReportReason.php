<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Why a visitor reports a listing. "people_visible" comes first: on naturist resorts, a
 * recognisable person on a photo is the report that cannot wait.
 */
enum ReportReason: string
{
    case PeopleVisible = 'people_visible';
    case Misleading = 'misleading';
    case Scam = 'scam';
    case Offensive = 'offensive';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::PeopleVisible => 'Une personne est reconnaissable sur une photo',
            self::Misleading => 'Annonce trompeuse (photos, prix, description)',
            self::Scam => 'Arnaque ou logement qui n’existe pas',
            self::Offensive => 'Contenu choquant ou illégal',
            self::Other => 'Autre raison',
        };
    }
}
