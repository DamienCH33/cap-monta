<?php

declare(strict_types=1);

namespace App\Enum;

enum AccommodationStatus: string
{
    /** En cours de saisie : visible du seul propriétaire. */
    case Draft = 'draft';

    /** Visible de tous, présent dans la recherche et le sitemap. */
    case Published = 'published';

    /** Retiré sans être supprimé : le logement est vendu, ou la saison est finie. */
    case Archived = 'archived';
}
