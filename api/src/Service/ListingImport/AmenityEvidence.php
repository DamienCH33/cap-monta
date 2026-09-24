<?php

declare(strict_types=1);

namespace App\Service\ListingImport;

use App\Enum\Amenity;

/**
 * Is an equipment really written in the listing? The model ticks boxes from impressions
 * ("tout confort" → wifi, television, parking: 20 ticks out of nowhere in the first evaluation,
 * 23/09). The PHP looks for the words themselves and unticks what it cannot find.
 * It ticks on its own only the few equipments whose words
 * leave no doubt (STRONG): the model finds, the PHP checks. An untick is cheap, the owner ticks
 * it back; a box ticked for nothing ends up promised to a tenant.
 */
final class AmenityEvidence
{
    /** Regular expressions on the folded text (lower case, no accents, words separated by one space). */
    private const array TERMS = [
        'climatisation' => ['clim', 'climatis\w*', 'air conditionne\w*'],
        'chauffage' => ['chauffage', 'chauffes?(?! eau)', 'chauffants?', 'radiateurs?', 'poele'],
        'television' => ['tv', 'tele', 'televiseurs?', 'televisions?', 'ecran plat'],
        'wifi' => ['wi ?fi', 'internet', 'box'],
        'lave-vaisselle' => ['lave ?vaisselle', 'machine a laver la vaisselle'],
        'micro-ondes' => ['micro ?ondes?'],
        'four' => ['fours?'],
        'cafetiere' => ['cafetieres?', 'cafe', 'nespresso', 'senseo', 'dolce ?gusto', 'tassimo', 'expresso', 'percolateur'],
        'terrasse' => ['terrasses?', 'deck'],
        'terrasse-couverte' => ['terrasses?(?: \w+){0,4} (?:couverte|fermee|abritee)s?', 'terrasses?(?: \w+){0,6} (?:une )?partie couverte', 'auvent', 'pergola'],
        'salon-de-jardin' => ['salons? de jardin', 'mobilier (?:de jardin|d exterieur|exterieur|de salon)', 'salons? (?:d )?exterieurs?', 'salle a manger d exterieure?', 'tables? de jardin'],
        'plancha' => ['planchas?'],
        'barbecue' => ['barbe?c\w*', 'bbq'],
        'douche-exterieure' => ['douches?(?: \w+){0,3} exterieure?s?', 'douches? solaires?'],
        'lave-linge' => ['lave ?linge', 'machine a laver(?! la vaisselle)', 'machine a laver la vaisselle et (?:le )?linge'],
        'parking' => ['parking', 'stationnement', 'places? (?:de |pour (?:la |une )?)?(?:voiture|parking)', 'garer'],
        'linge-fourni' => ['(?:linge|draps|serviettes)(?: \w+){0,3} (?:fournis?|inclus)'],
        'lit-bebe' => ['lits? (?:de )?bebes?', 'lits? parapluies?', 'berceau'],
        'velos' => ['velos?'],
    ];

    /**
     * Equipment whose words leave no doubt: when the listing writes them and the model forgot
     * them, the PHP ticks them itself. Not the others: "café" may be the one down the road,
     * "internet" the booking channel, "vélos" a rental shop.
     */
    public const array STRONG = [
        Amenity::AirConditioning, Amenity::Dishwasher, Amenity::Microwave, Amenity::WashingMachine,
        Amenity::OutdoorShower, Amenity::Plancha, Amenity::Barbecue, Amenity::CoveredTerrace,
    ];

    /** A word just before the match that cancels it ("pas de télé", "sans wifi"). */
    private const array NEGATIONS = ['pas', 'sans', 'aucun', 'aucune', 'ni', 'non'];

    /** A place for bikes is not bikes ("local à vélos", "garage à vélos"). */
    private const array NOT_BIKES = ['local', 'garage', 'abri', 'range', 'remise', 'parking'];

    public static function isWritten(Amenity $amenity, string $text): bool
    {
        $text = self::words($text);

        foreach (self::TERMS[$amenity->value] as $term) {
            if (preg_match_all('/(?<= )'.$term.'(?= )/', $text, $matches, \PREG_OFFSET_CAPTURE) < 1) {
                continue;
            }
            foreach ($matches[0] as [$found, $offset]) {
                // The three words before, in the same sentence ("… pas acceptés. Grande terrasse").
                $sentence = strrchr(' '.substr($text, 0, $offset), '|');
                $before = \array_slice(explode(' ', trim(false === $sentence ? substr($text, 0, $offset) : substr($sentence, 1))), -3);
                $cancelled = [] !== array_intersect($before, self::NEGATIONS)
                    || 1 === preg_match('/ (?:non|pas) /', ' '.$found.' ')
                    || (Amenity::Bikes === $amenity && [] !== array_intersect($before, self::NOT_BIKES));

                if (!$cancelled) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Lower case, no accents, words separated by one space, "|" between sentences.
     */
    public static function words(string $text): string
    {
        $text = (string) preg_replace('/[.!?;\n]+/', ' | ', MonthLabel::fold($text));

        return ' '.trim((string) preg_replace('/[^a-z0-9|]+/', ' ', $text)).' ';
    }
}
