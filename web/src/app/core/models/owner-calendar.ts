/** Calendrier d'un logement vu par son propriétaire (GET /api/owner/accommodations/{slug}/calendar). */
export interface OwnerCalendar {
  slug: string;
  /** Premier et dernier jour modifiables, "2026-09-22". */
  from: string;
  to: string;
  checkedAt: string | null;
  upToDate: boolean;
  periods: OwnerPeriod[];
}

export type PeriodSource = 'booking' | 'block' | 'import' | 'ical';

export interface OwnerPeriod {
  id: string;
  /** Premier jour indisponible, inclus. */
  start: string;
  /** Jour où le logement redevient libre, exclu. */
  end: string;
  source: PeriodSource;
  note: string | null;
  /** Seuls les blocages du propriétaire se retirent d'ici, jamais une réservation. */
  removable: boolean;
}

/** Une plage de jours, bornes [) comme l'API : end est le jour où le logement redevient libre. */
export interface DayRange {
  start: string;
  end: string;
}

const SOURCE_LABELS: Record<PeriodSource, string> = {
  booking: 'Réservation Cap Monta',
  block: 'Bloqué par vous',
  import: 'Importé',
  ical: 'Agenda synchronisé',
};

export function sourceLabel(source: PeriodSource): string {
  return SOURCE_LABELS[source];
}

/** "2026-07-04" à partir d'une date locale, sans passer par UTC. */
export function isoDay(date: Date): string {
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');

  return `${date.getFullYear()}-${month}-${day}`;
}

/** Date locale à minuit : new Date('2026-07-04') serait minuit UTC, donc la veille à Montréal. */
export function localDate(iso: string): Date {
  const [year, month, day] = iso.slice(0, 10).split('-').map(Number);

  return new Date(year, month - 1, day);
}

export function addDays(iso: string, days: number): string {
  const date = localDate(iso);
  date.setDate(date.getDate() + days);

  return isoDay(date);
}

/** Les deux jours cliqués, dans n'importe quel ordre, tous deux inclus. */
export function rangeBetween(first: string, last: string): DayRange {
  const [start, end] = first <= last ? [first, last] : [last, first];

  return { start, end: addDays(end, 1) };
}

/** La semaine de location qui contient ce jour : du samedi au samedi suivant. */
export function saturdayWeek(iso: string): DayRange {
  const date = localDate(iso);
  // getDay() : 6 pour samedi. On recule jusqu'au samedi précédent (ou on reste dessus).
  const back = (date.getDay() + 1) % 7;
  const start = addDays(iso, -back);

  return { start, end: addDays(start, 7) };
}

/** Nombre de jours de la plage, bornes [) : du 3 au 10, 7 jours. */
export function dayCount(range: DayRange): number {
  return Math.round(
    (localDate(range.end).getTime() - localDate(range.start).getTime()) / 86_400_000,
  );
}

export function overlapsAny(range: DayRange, periods: OwnerPeriod[]): boolean {
  return periods.some((period) => period.start < range.end && period.end > range.start);
}

/** Le dernier jour indisponible, pour l'affichage : l'API parle en [), les gens en jours inclus. */
export function lastDay(range: DayRange): string {
  return addDays(range.end, -1);
}
