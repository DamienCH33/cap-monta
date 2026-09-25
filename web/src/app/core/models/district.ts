export type DistrictArea = 'dunes' | 'central' | 'roadside';

/** Libellés des zones, dans l'ordre de la plage vers l'avenue. */
export const DISTRICT_AREAS: readonly { key: DistrictArea; label: string }[] = [
  { key: 'dunes', label: $localize`:@@area.dunes:Près des dunes / Océan` },
  { key: 'central', label: $localize`:@@area.central:Au cœur du domaine` },
  { key: 'roadside', label: $localize`:@@area.roadside:Côté avenue` },
];

export interface District {
  slug: string;
  name: string;
  resort: 'chm' | 'euronat';
  area: DistrictArea | null;
  accommodationCount: number;
  // Présents seulement sur /api/districts/{slug}.
  intro?: string | null;
  highlights?: string[];
  priceMin?: number | null;
  priceMax?: number | null;
}
