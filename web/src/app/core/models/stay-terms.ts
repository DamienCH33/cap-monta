/**
 * Les conditions du propriétaire et les frais en plus du loyer, montants en centimes.
 * Tout est facultatif : null = non précisé (jamais « gratuit »).
 */
export interface StayTerms {
  checkInFrom: string | null;
  checkOutBefore: string | null;
  depositPercent: number | null;
  securityDeposit: number | null;
  cancellationPolicy: string | null;
  cleaningFee: number | null;
  linenFee: number | null;
  touristTax: number | null;
  resortFee: number | null;
}

export const NO_TERMS: StayTerms = {
  checkInFrom: null,
  checkOutBefore: null,
  depositPercent: null,
  securityDeposit: null,
  cancellationPolicy: null,
  cleaningFee: null,
  linenFee: null,
  touristTax: null,
  resortFee: null,
};

export type ExtraCode = 'tourist_tax' | 'resort_fee' | 'cleaning' | 'linen';

export interface QuoteExtra {
  code: ExtraCode;
  amount: number;
  optional: boolean;
}

export const EXTRA_LABELS: Record<ExtraCode, string> = {
  tourist_tax: 'Taxe de séjour',
  resort_fee: 'Redevance du domaine',
  cleaning: 'Ménage de fin de séjour',
  linen: 'Linge de lit et de toilette',
};

/** Les montants à saisir, en euros ; les heures, en demi-heures de 7 h à 22 h. */
export const TIME_OPTIONS: string[] = Array.from({ length: 31 }, (_, i) => {
  const minutes = 7 * 60 + i * 30;

  return `${String(Math.floor(minutes / 60)).padStart(2, '0')}:${minutes % 60 ? '30' : '00'}`;
});

/** « 16:00 » → « 16 h », « 16:30 » → « 16 h 30 » */
export function timeLabel(time: string): string {
  const [hours, minutes] = time.split(':');

  return '00' === minutes ? `${Number(hours)} h` : `${Number(hours)} h ${minutes}`;
}

/** 88 → « 0,88 € », 30000 → « 300 € » */
export function euros(cents: number): string {
  const value = cents / 100;

  return (
    value.toLocaleString('fr-FR', {
      minimumFractionDigits: Number.isInteger(value) ? 0 : 2,
      maximumFractionDigits: 2,
    }) + ' €'
  );
}

/** Saisie en euros (« 0,88 », « 300 », « 4.5 ») → centimes ; vide → null ; illisible → NaN. */
export function toCents(input: string | null): number | null {
  const text = (input ?? '').trim().replace(/\s|€/g, '').replace(',', '.');
  if ('' === text) {
    return null;
  }
  if (!/^\d+(\.\d{1,2})?$/.test(text)) {
    return Number.NaN;
  }

  return Math.round(Number(text) * 100);
}

/** Centimes → saisie en euros (« 0,88 », « 300 ») ; null → vide. */
export function toEuroInput(cents: number | null): string {
  if (null === cents) {
    return '';
  }
  const value = cents / 100;

  return Number.isInteger(value) ? String(value) : value.toFixed(2).replace('.', ',');
}

/** Vrai si le propriétaire a précisé au moins une condition. */
export function hasTerms(terms: StayTerms | undefined): boolean {
  return !!terms && Object.values(terms).some((value) => null !== value && '' !== value);
}
