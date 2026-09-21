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
  description: string;
}

/** Création : le strict minimum pour un brouillon. Le serveur décide du slug et du statut. */
export interface NewAccommodation {
  resort: string;
  type: string;
  capacity: number;
  bedrooms: number;
  description?: string;
  district?: string | null;
}

/** Édition : seulement les champs modifiés. null efface le quartier ou la surface. */
export interface AccommodationChanges {
  type?: string;
  capacity?: number;
  bedrooms?: number;
  surface?: number | null;
  amenities?: string[];
  description?: string;
  district?: string | null;
}

const STATUS_LABELS: Record<AccommodationStatus, string> = {
  draft: 'Brouillon',
  published: 'En ligne',
  archived: 'Retiré du site',
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
  const parts = [plural(list.length, 'logement', 'logements')];

  if (count('published')) {
    parts.push(`${count('published')} en ligne`);
  }
  if (count('draft')) {
    parts.push(plural(count('draft'), 'brouillon', 'brouillons'));
  }
  if (count('archived')) {
    parts.push(plural(count('archived'), 'retiré', 'retirés'));
  }

  return parts.join(' · ');
}

export function plural(count: number, one: string, many: string): string {
  return `${count} ${count > 1 ? many : one}`;
}
