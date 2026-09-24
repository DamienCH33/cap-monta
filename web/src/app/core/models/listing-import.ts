import { toCents, toEuros } from './owner-rates';

/** L'assistant de lecture est-il utilisable ? (GET /api/owner/listing-imports/assistant) */
export interface AssistantStatus {
  available: boolean;
  /** disabled : pas de clé configurée ; paused : pause après des pannes du fournisseur. */
  reason: 'disabled' | 'paused' | null;
  until: string | null;
}

export type ImportStatus = 'pending' | 'done' | 'failed';

/** Une lecture d'annonce, telle que l'API la renvoie. */
export interface ListingImportView {
  id: string;
  status: ImportStatus;
  failure: string | null;
  /** Ce qu'il faut dire au propriétaire, en clair (attente, nouvel essai, échec). */
  message: string | null;
  attempts: number;
  /** Le texte collé, toujours rendu : il n'est jamais perdu. */
  text: string;
  proposal: Proposal | null;
  /** Déjà enregistrée : le logement rempli. */
  appliedTo: string | null;
  createdAt: string;
}

/** Ce que l'API a préparé pour l'écran de vérification. Prix en centimes, bornes [). */
export interface Proposal {
  listing: ProposalListing;
  periods: ProposalPeriod[];
  unavailable: ProposalRange[];
  questions: string[];
  contacts: string[];
}

export interface ProposalListing {
  resort: string | null;
  type: string | null;
  capacity: number | null;
  bedrooms: number | null;
  surface: number | null;
  district: string | null;
  amenities: string[];
  petsPolicy: string | null;
  otherFeatures: string[];
  description: string;
}

export interface ProposalPeriod {
  label: string;
  start: string | null;
  end: string | null;
  weeklyPrice: number | null;
  nightlyPrice: number | null;
  minimumNights: number;
  saturdayArrival: boolean;
  /** Prix du séjour entier écrit dans l'annonce, s'il y en avait un. */
  stayPrice: number | null;
  unitToConfirm: boolean;
  datesMissing: boolean;
  past: boolean;
  tooFar: boolean;
  selected: boolean;
}

export interface ProposalRange {
  start: string;
  end: string;
  tooFar: boolean;
  selected: boolean;
}

/** Une ligne de tarif sur l'écran : en euros, comme le propriétaire la lit et la corrige. */
export interface RateRow {
  label: string;
  keep: boolean;
  start: string;
  end: string;
  weekly: number | null;
  nightly: number | null;
  minimumNights: number;
  saturdayArrival: boolean;
  /** Ce que le propriétaire doit savoir sur la ligne, écrit pour lui. */
  hints: string[];
}

export interface RangeRow {
  keep: boolean;
  start: string;
  end: string;
  hint: string | null;
}

export interface Violation {
  propertyPath: string;
  message: string;
}

export function rateRows(periods: ProposalPeriod[]): RateRow[] {
  return periods.map((period) => ({
    label: period.label,
    keep: period.selected,
    start: period.start ?? '',
    end: period.end ?? '',
    weekly: toEuros(period.weeklyPrice),
    nightly: toEuros(period.nightlyPrice),
    minimumNights: period.minimumNights,
    saturdayArrival: period.saturdayArrival,
    hints: rateHints(period),
  }));
}

export function rateHints(period: ProposalPeriod): string[] {
  const hints: string[] = [];

  if (period.past) {
    hints.push('Période passée : pas reprise. Vos tarifs datent peut-être d’une autre année.');
  }
  if (period.tooFar) {
    hints.push('Au-delà de deux ans : les tarifs se saisissent plus tard.');
  }
  if (period.datesMissing) {
    hints.push('Dates à préciser : votre annonce ne les donne pas.');
  }
  if (period.unitToConfirm) {
    hints.push(
      'Votre annonce ne dit pas si c’est la semaine ou la nuit : proposé à la semaine, vérifiez.',
    );
  }
  if (null !== period.stayPrice) {
    const stay = toEuros(period.stayPrice);
    hints.push(
      null === period.weeklyPrice && null === period.nightlyPrice
        ? `${stay} € pour le séjour : indiquez les dates pour le convertir.`
        : `${stay} € pour le séjour entier, ramené ici à la semaine ou à la nuit.`,
    );
  }

  return hints;
}

export function rangeRows(ranges: ProposalRange[]): RangeRow[] {
  return ranges.map((range) => ({
    keep: range.selected,
    start: range.start,
    end: range.end,
    hint: range.tooFar ? 'Au-delà de 18 mois : le calendrier ne va pas si loin.' : null,
  }));
}

/** Les lignes gardées, prêtes pour l'API (centimes). */
export function ratesPayload(rows: RateRow[]): {
  start: string;
  end: string;
  weeklyPrice: number | null;
  nightlyPrice: number | null;
  minimumNights: number;
  saturdayArrival: boolean;
}[] {
  return rows
    .filter((row) => row.keep)
    .map((row) => ({
      start: row.start,
      end: row.end,
      weeklyPrice: toCents(row.weekly),
      nightlyPrice: toCents(row.nightly),
      minimumNights: Number(row.minimumNights),
      saturdayArrival: row.saturdayArrival,
    }));
}

export function rangesPayload(rows: RangeRow[]): { start: string; end: string }[] {
  return rows.filter((row) => row.keep).map((row) => ({ start: row.start, end: row.end }));
}

/**
 * Les refus de l'API parlent en position dans la liste envoyée (« periods[1].end ») : on les
 * ramène à la ligne affichée, en sautant les lignes décochées qui ne sont pas parties.
 */
export function rowErrors(
  violations: Violation[],
  key: 'periods' | 'unavailable',
  rows: { keep: boolean }[],
): Map<number, string[]> {
  const sent = rows.map((row, index) => (row.keep ? index : -1)).filter((index) => index >= 0);
  const errors = new Map<number, string[]>();

  for (const violation of violations) {
    const match = new RegExp(`^${key}\\[(\\d+)\\]`).exec(violation.propertyPath);
    const row = match ? sent[Number(match[1])] : undefined;
    if (undefined !== row) {
      errors.set(row, [...(errors.get(row) ?? []), violation.message]);
    }
  }

  return errors;
}

/** Les refus qui ne visent pas une ligne : le formulaire du logement, ou la requête entière. */
export function otherErrors(violations: Violation[]): Violation[] {
  return violations.filter((v) => !/^(periods|unavailable)\[/.test(v.propertyPath));
}
