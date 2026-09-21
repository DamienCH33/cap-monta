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
