import { PetsPolicy } from './accommodation';
import { StayTerms } from './stay-terms';

/** Un logement vu par son propriétaire, dans son espace : tous les statuts. */
export type AccommodationStatus = 'draft' | 'published' | 'archived';

export interface OwnerAccommodation {
  slug: string;
  status: AccommodationStatus;
  resort: string;
  type: string;
  district: string | null;
  districtSlug: string | null;
  capacity: number;
  bedrooms: number;
  surface: number | null;
  amenities: string[];
  petsPolicy: PetsPolicy;
  terms: StayTerms;
  description: string;
  /** Dans l'ordre : la première est la couverture. */
  photos: OwnerPhoto[];
  /** Dernière vérification du calendrier par le propriétaire ; null s'il ne l'a jamais fait. */
  calendarCheckedAt: string | null;
  /** Vérifié il y a moins de 30 jours : le badge « Calendrier à jour » s'affiche. */
  calendarUpToDate: boolean;
}

/** « vérifié aujourd'hui », « vérifié il y a 34 jours », « jamais vérifié ». */
export function checkedLabel(checkedAt: string | null, now = new Date()): string {
  if (null === checkedAt) {
    return $localize`:@@owner.common.checked-never:jamais vérifié`;
  }

  const day = (date: Date) =>
    new Date(date.getFullYear(), date.getMonth(), date.getDate()).getTime();
  const days = Math.round((day(now) - day(new Date(checkedAt))) / 86_400_000);

  if (days <= 0) {
    return $localize`:@@owner.common.checked-today:vérifié aujourd'hui`;
  }

  return 1 === days
    ? $localize`:@@owner.common.checked-yesterday:vérifié hier`
    : $localize`:@@owner.common.checked-days-ago:vérifié il y a ${days}:days: jours`;
}

/** Les calendriers à vérifier d'abord, du plus ancien au plus récent ; « jamais » en tête. */
export function byCalendarUrgency(a: OwnerAccommodation, b: OwnerAccommodation): number {
  if (a.calendarUpToDate !== b.calendarUpToDate) {
    return a.calendarUpToDate ? 1 : -1;
  }

  return (a.calendarCheckedAt ?? '').localeCompare(b.calendarCheckedAt ?? '');
}

/** Une photo du logement : grande version pour la fiche, miniature pour les cartes. */
export interface OwnerPhoto {
  id: string;
  url: string;
  thumbUrl: string;
  width: number;
  height: number;
}

/** Création : tout peut être envoyé d'une traite. Le serveur décide du slug et du statut. */
export interface NewAccommodation {
  resort: string;
  type: string;
  capacity: number;
  bedrooms: number;
  surface?: number | null;
  amenities?: string[];
  petsPolicy?: PetsPolicy;
  description?: string;
  district?: string | null;
  terms?: StayTerms;
}

/** Édition : seulement les champs modifiés. null efface le quartier ou la surface. */
export interface AccommodationChanges {
  type?: string;
  capacity?: number;
  bedrooms?: number;
  surface?: number | null;
  amenities?: string[];
  petsPolicy?: PetsPolicy;
  description?: string;
  district?: string | null;
  /** Remplace toutes les conditions d'un coup. */
  terms?: StayTerms;
}

/** Un quartier, tel que les formulaires de l'espace propriétaire en ont besoin. */
export interface DistrictOption {
  slug: string;
  name: string;
  resort: string;
}

const STATUS_LABELS: Record<AccommodationStatus, string> = {
  draft: $localize`:@@owner.common.status-draft:Brouillon`,
  published: $localize`:@@owner.common.status-published:En ligne`,
  archived: $localize`:@@owner.common.status-archived:Retiré du site`,
};

export function statusLabel(status: AccommodationStatus): string {
  return STATUS_LABELS[status];
}

/** Le filtre de la liste, dans l'adresse : /mon-espace/logements?statut=en-ligne */
export const STATUS_PARAMS: Record<AccommodationStatus, string> = {
  published: 'en-ligne',
  draft: 'brouillons',
  archived: 'retires',
};

export function statusFromParam(value: string | null): AccommodationStatus | null {
  const found = (Object.keys(STATUS_PARAMS) as AccommodationStatus[]).find(
    (status) => STATUS_PARAMS[status] === value,
  );

  return found ?? null;
}

/** « 4 logements · 3 en ligne · 1 brouillon » : utilisé par Mon espace et Mes logements. */
export function summarize(list: OwnerAccommodation[]): string {
  const count = (status: AccommodationStatus) => list.filter((a) => a.status === status).length;
  const listings = list.length;
  const parts = [
    listings > 1
      ? $localize`:@@owner.common.listings.other:${listings}:count: logements`
      : $localize`:@@owner.common.listings.one:${listings}:count: logement`,
  ];

  const online = count('published');
  if (online) {
    parts.push($localize`:@@owner.common.online-count:${online}:count: en ligne`);
  }
  const drafts = count('draft');
  if (drafts) {
    parts.push(
      drafts > 1
        ? $localize`:@@owner.common.drafts.other:${drafts}:count: brouillons`
        : $localize`:@@owner.common.drafts.one:${drafts}:count: brouillon`,
    );
  }
  const retired = count('archived');
  if (retired) {
    parts.push(
      retired > 1
        ? $localize`:@@owner.common.retired.other:${retired}:count: retirés`
        : $localize`:@@owner.common.retired.one:${retired}:count: retiré`,
    );
  }

  return parts.join(' · ');
}

/** « 4 personnes », « 1 personne » (0 et 1 au singulier, comme en français). */
export function peopleCount(count: number): string {
  return count > 1
    ? $localize`:@@owner.common.people.other:${count}:count: personnes`
    : $localize`:@@owner.common.people.one:${count}:count: personne`;
}

/** « 2 chambres », « 0 chambre ». */
export function bedroomCount(count: number): string {
  return count > 1
    ? $localize`:@@owner.common.bedrooms.other:${count}:count: chambres`
    : $localize`:@@owner.common.bedrooms.one:${count}:count: chambre`;
}

/** Aide française historique : préférer peopleCount / bedroomCount, déjà traduites. */
export function plural(count: number, one: string, many: string): string {
  return `${count} ${count > 1 ? many : one}`;
}
