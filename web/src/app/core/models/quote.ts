import { ExtraCode, QuoteExtra } from './stay-terms';

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
  /** Frais déclarés par le propriétaire, calculés pour ce séjour. */
  extras: QuoteExtra[];
  /** Loyer + frais obligatoires ; null tant que le loyer est « à convenir ». */
  estimatedTotal: number | null;
  /** Frais obligatoires dont le propriétaire n'a pas donné le montant. */
  unknownFees: ExtraCode[];
}
