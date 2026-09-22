/** Tarifs d'un logement vus par son propriétaire. Prix en centimes, dates en bornes [). */
export interface OwnerRates {
  slug: string;
  today: string;
  periods: RatePeriod[];
  /** La copie à proposer : la dernière année saisie vers la suivante. */
  copySuggestion: { from: number; to: number } | null;
  /** Présents seulement dans la réponse d'une copie. */
  copied?: number;
  skipped?: number;
}

export interface RatePeriod {
  id: string;
  start: string;
  end: string;
  weeklyPrice: number | null;
  nightlyPrice: number | null;
  minimumNights: number;
  saturdayArrival: boolean;
  past: boolean;
}

/** Ce que le formulaire envoie : prix en centimes, null quand le champ est vide. */
export interface RateInput {
  start: string;
  end: string;
  weeklyPrice: number | null;
  nightlyPrice: number | null;
  minimumNights: number;
  saturdayArrival: boolean;
}

/** Euros saisis → centimes ; vide ou non numérique → null. */
export function toCents(euros: number | string | null): number | null {
  if (null === euros || '' === euros) {
    return null;
  }

  const value = Number(euros);

  return Number.isFinite(value) ? Math.round(value * 100) : null;
}

export function toEuros(cents: number | null): number | null {
  return null === cents ? null : Math.round(cents / 100);
}

/** La période qui vient naturellement après les autres : là où s'arrête la dernière, une semaine. */
export function nextPeriodDates(
  periods: RatePeriod[],
  today: string,
): { start: string; end: string } {
  const last = periods
    .filter((p) => !p.past)
    .map((p) => p.end)
    .sort()
    .at(-1);
  const start = last && last > today ? last : today;
  const date = new Date(`${start}T00:00:00`);
  date.setDate(date.getDate() + 7);
  const end = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;

  return { start, end };
}

/** Les périodes regroupées par année de début, pour une lecture « saison par saison ». */
export function byYear(periods: RatePeriod[]): { year: number; periods: RatePeriod[] }[] {
  const groups = new Map<number, RatePeriod[]>();

  for (const period of periods) {
    const year = Number(period.start.slice(0, 4));
    groups.set(year, [...(groups.get(year) ?? []), period]);
  }

  return [...groups.entries()]
    .sort(([a], [b]) => a - b)
    .map(([year, list]) => ({ year, periods: list }));
}
