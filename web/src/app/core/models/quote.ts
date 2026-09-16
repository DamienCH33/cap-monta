export type RefusalReason = 'unavailable' | 'too_many_guests' | 'stay_too_short';

export interface Quote {
  slug: string;
  arrival: string;
  departure: string;
  nights: number;
  guests: number;
  maxCapacity: number;
  minimumNights: number;
  available: boolean;
  total: number | null;
  refusal: RefusalReason | null;
}
