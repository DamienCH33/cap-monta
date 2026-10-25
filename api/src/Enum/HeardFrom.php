<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * « Comment avez-vous connu Cap Monta ? », asked once at registration, optional. A choice in a
 * list, never free text: it counts which channel brings owners (app:owners:sources), nothing more.
 */
enum HeardFrom: string
{
    case FacebookGroup = 'facebook_group';
    case OtherOwner = 'other_owner';
    case CoteMonta = 'cote_monta';
    case Press = 'press';
    case Search = 'search';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::FacebookGroup => 'Groupe Facebook',
            self::OtherOwner => 'Un autre propriétaire',
            self::CoteMonta => 'CôtéMonta',
            self::Press => 'Presse',
            self::Search => 'Recherche Google',
            self::Other => 'Autre',
        };
    }
}
