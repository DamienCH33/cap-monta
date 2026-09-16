export interface Accommodation {
  slug: string;
  resort: string;
  type: string;
  district: string | null;
  capacity: number;
  maxCapacity: number;
  bedrooms: number;
  surface: number | null;
  amenities: string[];
  description: string;
  priceFrom: number | null;
  availability: AvailabilityWeek[];
}

export interface JsonLdCollection<T> {
  member: T[];
  totalItems: number;
}

export interface AvailabilityWeek {
  start: string;
  free: boolean;
}
