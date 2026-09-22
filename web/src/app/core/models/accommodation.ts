import { DistrictArea } from './district';
export interface Accommodation {
  slug: string;
  resort: string;
  type: string;
  district: string | null;
  districtSlug: string | null;
  districtArea: DistrictArea | null;
  capacity: number;
  maxCapacity: number;
  bedrooms: number;
  surface: number | null;
  amenities: string[];
  petsPolicy: PetsPolicy;
  description: string;
  priceFrom: number | null;
  availability: AvailabilityWeek[];
  pricePeriods: PricePeriod[];
  /** Photo de couverture : présente sur les cartes comme sur la fiche, null sans photo. */
  cover: Photo | null;
  /** Toutes les photos, couverture en premier. Vide dans la liste de recherche. */
  photos: Photo[];
  /** Le propriétaire a vérifié son calendrier il y a moins de 30 jours. */
  calendarUpToDate: boolean;
}

export interface Photo {
  url: string;
  thumbUrl: string;
  width: number;
  height: number;
}

export interface JsonLdCollection<T> {
  member: T[];
  totalItems: number;
  view?: { last?: string; next?: string; previous?: string };
}

export interface AvailabilityWeek {
  start: string;
  free: boolean;
}

export interface PricePeriod {
  startDate: string;
  endDate: string;
  weeklyPrice: number | null;
  nightlyPrice: number | null;
  minimumNights: number;
  /** Le propriétaire préfère les arrivées le samedi sur cette période. */
  saturdayArrival: boolean;
}

const TYPE_LABELS: Record<string, string> = {
  mobile_home: 'Mobil-home',
  bungalow: 'Bungalow',
  caravan: 'Caravane',
};

export function typeLabel(type: string): string {
  return TYPE_LABELS[type] ?? 'Logement';
}

/** Ce que le propriétaire a décidé pour les animaux. */
export type PetsPolicy = 'allowed' | 'on_request' | 'not_allowed';

export const PETS_POLICIES: { value: PetsPolicy; label: string; hint: string }[] = [
  { value: 'allowed', label: 'Animaux acceptés', hint: 'Les animaux sont les bienvenus.' },
  {
    value: 'on_request',
    label: 'Animaux sur demande',
    hint: 'Le voyageur le précise dans sa demande, vous décidez au cas par cas.',
  },
  {
    value: 'not_allowed',
    label: 'Animaux non acceptés',
    hint: 'Les demandes avec un animal sont refusées automatiquement.',
  },
];

export function petsPolicyLabel(policy: PetsPolicy): string {
  return PETS_POLICIES.find((p) => p.value === policy)?.label ?? 'Animaux sur demande';
}

export interface AccommodationPage {
  items: Accommodation[];
  total: number;
  page: number;
  totalPages: number;
}
