import { ParamMap, Params } from '@angular/router';

export type AccommodationTypeKey = 'caravan' | 'mobile_home' | 'bungalow';
export type SortOrder = 'price_asc' | 'price_desc';
export type DistrictSide = 'ocean' | 'forest';

export interface SearchFilters {
  types: AccommodationTypeKey[];
  districts: string[];
  bedrooms: number;
  amenities: string[];
  order: SortOrder | null;
}

export interface FilterChip {
  key: string;
  label: string;
  /** Les filtres tels qu'ils seront une fois cette puce retirée. */
  without: SearchFilters;
}

export const NO_FILTERS: SearchFilters = {
  types: [],
  districts: [],
  bedrooms: 0,
  amenities: [],
  order: null,
};

/** `key` : valeur envoyée à l'API. `slug` : valeur affichée dans l'URL du site. */
export const ACCOMMODATION_TYPES: readonly {
  key: AccommodationTypeKey;
  slug: string;
  label: string;
  hint: string;
}[] = [
  { key: 'caravan', slug: 'caravane', label: 'Caravane', hint: '1 chambre' },
  { key: 'mobile_home', slug: 'mobil-home', label: 'Mobil-home', hint: '2 à 3 chambres' },
  { key: 'bungalow', slug: 'bungalow', label: 'Bungalow', hint: '3 chambres' },
];

/** Provisoire : à déplacer dans l'API avec les pages quartier (écran 04). */
export const DISTRICT_SIDES: readonly {
  side: DistrictSide;
  label: string;
  districts: readonly string[];
}[] = [
  { side: 'ocean', label: 'Côté océan', districts: ['Europa', 'Californie', 'Médoc'] },
  { side: 'forest', label: 'Côté forêt', districts: ['Bruyère', 'Lalande', 'Écureuils'] },
];

export const AMENITIES: readonly { key: string; label: string }[] = [
  { key: 'climatisation', label: 'Climatisation' },
  { key: 'lave-vaisselle', label: 'Lave-vaisselle' },
  { key: 'micro-ondes', label: 'Micro-ondes' },
  { key: 'terrasse', label: 'Terrasse' },
  { key: 'television', label: 'Télévision' },
  { key: 'wifi', label: 'Wi-Fi' },
  { key: 'plancha', label: 'Plancha' },
];

export const SORT_OPTIONS: readonly { value: SortOrder; slug: string; label: string }[] = [
  { value: 'price_asc', slug: 'prix-croissant', label: 'Prix / semaine croissant' },
  { value: 'price_desc', slug: 'prix-decroissant', label: 'Prix / semaine décroissant' },
];

export const MAX_BEDROOMS_FILTER = 3;

export function activeFilterCount(filters: SearchFilters): number {
  return (
    filters.types.length +
    filters.districts.length +
    (filters.bedrooms > 0 ? 1 : 0) +
    filters.amenities.length
  );
}

/** Ajoute la valeur si elle est absente, la retire si elle est présente. */
export function toggle<T>(list: readonly T[], value: T): T[] {
  return list.includes(value) ? list.filter((item) => item !== value) : [...list, value];
}

export function filtersFromQuery(params: ParamMap): SearchFilters {
  const bedrooms = Math.trunc(Number(params.get('chambres')) || 0);

  const types = values(params, 'type')
    .map((slug) => ACCOMMODATION_TYPES.find((type) => type.slug === slug)?.key)
    .filter((key): key is AccommodationTypeKey => key !== undefined);

  const amenities = values(params, 'equipements').filter((key) =>
    AMENITIES.some((amenity) => amenity.key === key),
  );

  return {
    types: unique(types),
    districts: unique(values(params, 'quartier')),
    bedrooms: Math.min(Math.max(bedrooms, 0), MAX_BEDROOMS_FILTER),
    amenities: unique(amenities),
    order: SORT_OPTIONS.find((option) => option.slug === params.get('tri'))?.value ?? null,
  };
}

export function filtersToQuery(filters: SearchFilters): Params {
  return {
    type:
      filters.types.length > 0
        ? filters.types.map(
            (key) => ACCOMMODATION_TYPES.find((type) => type.key === key)?.slug ?? key,
          )
        : null,
    quartier: filters.districts.length > 0 ? filters.districts : null,
    chambres: filters.bedrooms > 0 ? filters.bedrooms : null,
    equipements: filters.amenities.length > 0 ? filters.amenities : null,
    tri: SORT_OPTIONS.find((option) => option.value === filters.order)?.slug ?? null,
  };
}

export function filterChips(filters: SearchFilters): FilterChip[] {
  return [
    ...filters.types.map((key) => ({
      key: `type-${key}`,
      label: ACCOMMODATION_TYPES.find((type) => type.key === key)?.label ?? key,
      without: { ...filters, types: filters.types.filter((item) => item !== key) },
    })),
    ...filters.districts.map((district) => ({
      key: `district-${district}`,
      label: district,
      without: { ...filters, districts: filters.districts.filter((item) => item !== district) },
    })),
    ...(filters.bedrooms > 0
      ? [
          {
            key: 'bedrooms',
            label: `${filters.bedrooms} ch. et +`,
            without: { ...filters, bedrooms: 0 },
          },
        ]
      : []),
    ...filters.amenities.map((key) => ({
      key: `amenity-${key}`,
      label: AMENITIES.find((amenity) => amenity.key === key)?.label ?? key,
      without: { ...filters, amenities: filters.amenities.filter((item) => item !== key) },
    })),
  ];
}

/** Les valeurs non vides d'un paramètre, qu'il soit présent une fois ou plusieurs. */
function values(params: ParamMap, name: string): string[] {
  return params
    .getAll(name)
    .filter((value): value is string => typeof value === 'string' && value.trim() !== '');
}

function unique<T>(list: T[]): T[] {
  return [...new Set(list)];
}

export function districtSide(district: string | null): DistrictSide | null {
  if (!district) {
    return null;
  }

  return DISTRICT_SIDES.find((zone) => zone.districts.includes(district))?.side ?? null;
}
