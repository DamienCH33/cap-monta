export type RefusalReason =
  'unavailable' | 'too_many_guests' | 'stay_too_short' | 'pets_not_allowed';

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
  /** Arrivée hors de la préférence du propriétaire (le samedi) : dit, jamais bloquant. */
  outsideRules: boolean;
}
