import { ParamMap, Params } from '@angular/router';

export type AccommodationTypeKey = 'caravan' | 'mobile_home' | 'bungalow' | 'chalet' | 'studio';
export type SortOrder = 'price_asc' | 'price_desc';

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
  { key: 'chalet', slug: 'chalet', label: 'Chalet', hint: 'surtout à Euronat' },
  { key: 'studio', slug: 'studio', label: 'Studio', hint: '1 pièce ou 2 pièces' },
];

export type AmenityGroup = 'confort' | 'cuisine' | 'exterieur' | 'pratique';

export interface Amenity {
  key: string;
  label: string;
  group: AmenityGroup;
  /** Proposé dans les filtres de la recherche : les plus demandés, pas toute la liste. */
  filter: boolean;
}

/**
 * Liste fermée, volontairement : un texte libre donnerait « clim », « climatisation » et
 * « Clim réversible » pour le même équipement, et le filtre de la recherche ne trouverait
 * plus rien. Le reste se dit dans la description. Les clés ne changent jamais : elles sont
 * en base et dans les liens de recherche partagés.
 */
export const AMENITIES: readonly Amenity[] = [
  { key: 'climatisation', label: 'Climatisation', group: 'confort', filter: true },
  { key: 'chauffage', label: 'Chauffage', group: 'confort', filter: false },
  { key: 'television', label: 'Télévision', group: 'confort', filter: false },
  { key: 'wifi', label: 'Wi-Fi', group: 'confort', filter: true },
  { key: 'lave-vaisselle', label: 'Lave-vaisselle', group: 'cuisine', filter: true },
  { key: 'micro-ondes', label: 'Micro-ondes', group: 'cuisine', filter: false },
  { key: 'four', label: 'Four', group: 'cuisine', filter: false },
  { key: 'cafetiere', label: 'Cafetière', group: 'cuisine', filter: false },
  { key: 'terrasse', label: 'Terrasse', group: 'exterieur', filter: true },
  { key: 'terrasse-couverte', label: 'Terrasse couverte', group: 'exterieur', filter: false },
  { key: 'salon-de-jardin', label: 'Salon de jardin', group: 'exterieur', filter: false },
  { key: 'plancha', label: 'Plancha', group: 'exterieur', filter: true },
  { key: 'barbecue', label: 'Barbecue', group: 'exterieur', filter: false },
  { key: 'douche-exterieure', label: 'Douche extérieure', group: 'exterieur', filter: false },
  { key: 'lave-linge', label: 'Lave-linge', group: 'pratique', filter: true },
  { key: 'parking', label: 'Place de parking', group: 'pratique', filter: true },
  { key: 'linge-fourni', label: 'Linge de lit fourni', group: 'pratique', filter: false },
  { key: 'lit-bebe', label: 'Lit bébé', group: 'pratique', filter: true },
  { key: 'velos', label: 'Vélos à disposition', group: 'pratique', filter: false },
];

export const AMENITY_GROUPS: readonly { key: AmenityGroup; label: string }[] = [
  { key: 'confort', label: 'Confort' },
  { key: 'cuisine', label: 'Cuisine' },
  { key: 'exterieur', label: 'Extérieur' },
  { key: 'pratique', label: 'Pratique' },
];

/** « television » → « Télévision » ; une clé inconnue s'affiche telle quelle. */
export function amenityLabel(key: string): string {
  return AMENITIES.find((amenity) => amenity.key === key)?.label ?? key;
}

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

export function filterChips(
  filters: SearchFilters,
  districtLabel: (value: string) => string = (value) => value,
): FilterChip[] {
  return [
    ...filters.types.map((key) => ({
      key: `type-${key}`,
      label: ACCOMMODATION_TYPES.find((type) => type.key === key)?.label ?? key,
      without: { ...filters, types: filters.types.filter((item) => item !== key) },
    })),
    ...filters.districts.map((district) => ({
      key: `district-${district}`,
      label: districtLabel(district),
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
