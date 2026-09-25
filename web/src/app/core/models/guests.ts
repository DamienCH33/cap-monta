import { ParamMap } from '@angular/router';

export interface Guests {
  adults: number;
  children: number;
  infants: number;
  pets: number;
}

export const NO_GUESTS: Guests = { adults: 0, children: 0, infants: 0, pets: 0 };

/** Les personnes qui occupent un couchage : les bébés n'en font pas partie. */
export function travellerCount(guests: Guests): number {
  return guests.adults + guests.children;
}

export function guestsSummary(guests: Guests): string {
  const parts: string[] = [];
  const travellers = travellerCount(guests);

  if (travellers > 0) {
    parts.push(
      1 === travellers
        ? $localize`:@@guests.traveller.one:1 voyageur`
        : $localize`:@@guests.traveller.other:${travellers}:count: voyageurs`,
    );
  }
  if (guests.infants > 0) {
    parts.push(
      1 === guests.infants
        ? $localize`:@@guests.infant.one:1 bébé`
        : $localize`:@@guests.infant.other:${guests.infants}:count: bébés`,
    );
  }
  if (guests.pets > 0) {
    parts.push(
      1 === guests.pets
        ? $localize`:@@guests.pet.one:1 animal`
        : $localize`:@@guests.pet.other:${guests.pets}:count: animaux`,
    );
  }

  return parts.join(', ');
}

export function guestsFromQuery(params: ParamMap): Guests {
  const read = (name: string): number => Math.max(0, Math.trunc(Number(params.get(name)) || 0));

  const adults = read('adultes');
  const children = read('enfants');

  return {
    // Anciens liens : seul « voyageurs » existait, on le lit comme des adultes.
    adults: adults === 0 && children === 0 ? read('voyageurs') : adults,
    children,
    infants: read('bebes'),
    pets: read('animaux'),
  };
}

export function guestsToQuery(guests: Guests): Record<string, number | null> {
  return {
    voyageurs: travellerCount(guests) || null,
    adultes: guests.adults || null,
    enfants: guests.children || null,
    bebes: guests.infants || null,
    animaux: guests.pets || null,
  };
}
