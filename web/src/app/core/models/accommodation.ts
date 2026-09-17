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
  description: string;
  priceFrom: number | null;
  availability: AvailabilityWeek[];
  pricePeriods: PricePeriod[];
}

export interface JsonLdCollection<T> {
  member: T[];
  totalItems: number;
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
}

const TYPE_LABELS: Record<string, string> = {
  mobile_home: 'Mobil-home',
  bungalow: 'Bungalow',
  caravan: 'Caravane',
};

export function typeLabel(type: string): string {
  return TYPE_LABELS[type] ?? 'Logement';
}
