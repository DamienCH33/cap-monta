<?php

declare(strict_types=1);

namespace App\Reference;

/**
 * The sectors of Euronat where owners have a home, read on the official plan (euronat.com,
 * plan 2025, checked on the PDF on 29/09/2026) and on the resort's chalets and villas page:
 * - the chalet and villa villages, "côté plage" first (Amérique du Nord with direct access to
 *   the north beach, Amérique du Sud, Afrique with the south beach a short walk away,
 *   Afrique II), then "côté village" near the shops (Europe, Asie, Océanie), then Polynésie,
 *   next to the reception;
 * - the seven parks of the "Mobile Homes" area, with their numbered lots.
 * Left out: Camping, Camping avec électricité, Ifs and Caravaning, pitches rented by the resort
 * itself, not owners' homes (ADR 026).
 *
 * Unlike the CHM, choosing one stays optional: an owner who does not find his sector must still
 * be able to publish. Slugs are prefixed with "euronat-": Polynésie also exists at the CHM, and
 * a slug is unique across both resorts.
 */
final class EuronatDistricts
{
    /** @var list<array{string, list<string>}> name, what the plan and euronat.com say of it */
    public const array ALL = [
        ['Amérique du Nord', ['Côté plage', 'Accès direct à la plage Nord']],
        ['Amérique du Sud', ['Côté plage']],
        ['Afrique', ['Côté plage', 'La plage Sud à quelques pas']],
        ['Afrique II', []],
        ['Europe', ['Côté village', 'Près des commerces']],
        ['Asie', ['Côté village', 'Près des commerces']],
        ['Océanie', ['Côté village', 'Près des commerces']],
        ['Polynésie', ["Près de l'accueil"]],
        ['Parc des Lauriers', ['Zone des mobil-homes']],
        ['Parc des Mélèzes', ['Zone des mobil-homes']],
        ['Parc des Oyats', ['Zone des mobil-homes']],
        ['Parc des Acacias', ['Zone des mobil-homes']],
        ['Parc des Mimosas', ['Zone des mobil-homes']],
        ['Parc des Châtaigniers', ['Zone des mobil-homes']],
        ['Parc des Albizzias', ['Zone des mobil-homes']],
    ];

    /** "Amérique du Nord" → "euronat-amerique-du-nord". */
    public static function slug(string $name): string
    {
        return 'euronat-'.ChmDistricts::slug($name);
    }
}
