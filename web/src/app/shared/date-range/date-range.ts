import { Component, computed, ElementRef, inject, input, model, signal } from '@angular/core';

import { BusyPeriod } from '../../core/models/availability';
import { plusDays, today } from '../../core/models/stay-dates';
import { currentLang, currentLangOption } from '../../core/i18n/lang';

interface Day {
  iso: string;
  label: number;
  /** « samedi 4 juillet 2026 », pour les lecteurs d'écran. */
  name: string;
  saturday: boolean;
  /** Avant le premier jour possible (aujourd'hui, ou demain pour une demande). */
  past: boolean;
  /** Nuit déjà prise dans le calendrier du logement. */
  taken?: boolean;
}

interface Month {
  key: string;
  title: string;
  blanks: number[];
  days: Day[];
}

const MONTHS = [
  'janvier',
  'février',
  'mars',
  'avril',
  'mai',
  'juin',
  'juillet',
  'août',
  'septembre',
  'octobre',
  'novembre',
  'décembre',
];
const SHORT_MONTHS = [
  'janv.',
  'févr.',
  'mars',
  'avr.',
  'mai',
  'juin',
  'juil.',
  'août',
  'sept.',
  'oct.',
  'nov.',
  'déc.',
];
const SHORT_DAYS = ['dim.', 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.'];
const DAYS = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];

/** Le calendrier va aussi loin que celui des propriétaires. */
const MONTHS_AHEAD = 18;

function parts(iso: string): [number, number, number] {
  const [year, month, day] = iso.split('-').map(Number);

  return [year, month, day];
}

/** Dans les autres langues que le français, le navigateur sait écrire les dates. */
function intl(iso: string, options: Intl.DateTimeFormatOptions): string {
  const [year, month, day] = parts(iso);

  return new Intl.DateTimeFormat(currentLangOption().tag, options).format(
    new Date(year, month - 1, day),
  );
}

/** « sam. 4 juil. » */
export function shortDate(iso: string): string {
  if ('fr' !== currentLang()) {
    return intl(iso, { weekday: 'short', day: 'numeric', month: 'short' });
  }
  const [year, month, day] = parts(iso);
  const weekday = new Date(year, month - 1, day).getDay();

  return `${SHORT_DAYS[weekday]} ${day} ${SHORT_MONTHS[month - 1]}`;
}

export function nights(arrival: string, departure: string): number {
  const [y1, m1, d1] = parts(arrival);
  const [y2, m2, d2] = parts(departure);

  return Math.round((Date.UTC(y2, m2 - 1, d2) - Date.UTC(y1, m1 - 1, d1)) / 86_400_000);
}

/**
 * Choix des dates d'un séjour en un seul calendrier, en français : un clic pour l'arrivée, un
 * second pour le départ. Les samedis ressortent (la plupart des locations du CHM vont du samedi
 * au samedi). Remplace les champs de date du navigateur, qui s'affichaient parfois au format
 * américain (mm/dd/yyyy).
 */
@Component({
  selector: 'cm-date-range',
  templateUrl: './date-range.html',
  styleUrl: './date-range.scss',
  host: {
    '(document:click)': 'onDocumentClick($event)',
    '(document:keydown.escape)': 'close()',
  },
})
export class DateRange {
  private readonly host = inject<ElementRef<HTMLElement>>(ElementRef);

  readonly arrival = model('');
  readonly departure = model('');
  /** Périodes déjà prises (fiche d'un logement) : ces nuits sont barrées et hachurées. */
  readonly busy = input<BusyPeriod[]>([]);
  /** Premier jour d'arrivée possible ; aujourd'hui par défaut. */
  readonly min = input('');
  /** Un seul mois, panneau étroit : pour une colonne comme le formulaire de demande. */
  readonly compact = input(false);
  readonly open = signal(false);
  readonly takenSuffix = $localize`:@@dates.taken-suffix:, déjà pris`;

  // Du lundi au dimanche ; 2026-01-05 est un lundi.
  readonly weekdays =
    'fr' === currentLang()
      ? ['lu', 'ma', 'me', 'je', 've', 'sa', 'di']
      : Array.from({ length: 7 }, (_, i) =>
          intl(`2026-01-${String(5 + i).padStart(2, '0')}`, { weekday: 'short' }).slice(0, 2),
        );
  private readonly today = today();

  /** Premier mois affiché, en mois depuis le mois courant. */
  readonly offset = signal(0);

  readonly label = computed(() => {
    const arrival = this.arrival();
    const departure = this.departure();

    if ('' === arrival) {
      return null;
    }

    return '' === departure
      ? `${shortDate(arrival)} → ?`
      : `${shortDate(arrival)} → ${shortDate(departure)}`;
  });

  readonly summary = computed(() => {
    const arrival = this.arrival();
    const departure = this.departure();
    if ('' === arrival) {
      return $localize`:@@dates.pick-arrival:Choisissez le jour d’arrivée.`;
    }
    if ('' === departure) {
      return $localize`:@@dates.pick-departure:Choisissez le jour de départ.`;
    }
    const count = nights(arrival, departure);

    const nightsLabel =
      1 === count
        ? $localize`:@@dates.night.one:1 nuit`
        : $localize`:@@dates.night.other:${count}:count: nuits`;

    return `${shortDate(arrival)} → ${shortDate(departure)} · ${nightsLabel}`;
  });

  readonly months = computed<Month[]>(() =>
    this.compact()
      ? [this.month(this.offset())]
      : [this.month(this.offset()), this.month(this.offset() + 1)],
  );
  private readonly shown = computed(() => (this.compact() ? 1 : 2));
  readonly canGoBack = computed(() => this.offset() > 0);
  readonly canGoForward = computed(() => this.offset() < MONTHS_AHEAD - this.shown());

  /**
   * Pendant le choix du départ : le dernier départ possible, c'est-à-dire le début de la
   * prochaine période prise (on part le matin où d'autres arrivent). Null s'il n'y en a pas.
   */
  private readonly lastDeparture = computed(() => {
    const arrival = this.arrival();
    if ('' === arrival || '' !== this.departure()) {
      return null;
    }
    const starts = this.busy()
      .map((period) => period.start)
      .filter((start) => start > arrival)
      .sort();

    return starts[0] ?? null;
  });

  /** Un jour qu'on ne peut pas cliquer maintenant. */
  isDisabled(day: Day): boolean {
    if (day.past) {
      return true;
    }
    const arrival = this.arrival();
    const choosingDeparture = '' !== arrival && '' === this.departure() && day.iso > arrival;
    if (choosingDeparture) {
      const last = this.lastDeparture();

      return null !== last && day.iso > last;
    }

    return !!day.taken;
  }

  toggle(): void {
    this.open.update((isOpen) => !isOpen);
  }

  close(): void {
    this.open.set(false);
  }

  onDocumentClick(event: MouseEvent): void {
    if (this.open() && !this.host.nativeElement.contains(event.target as Node)) {
      this.close();
    }
  }

  move(step: number): void {
    this.offset.update((value) => Math.min(MONTHS_AHEAD - this.shown(), Math.max(0, value + step)));
  }

  /** Premier clic : l'arrivée. Second clic, plus tard : le départ, et le calendrier se referme. */
  pick(day: Day): void {
    if (this.isDisabled(day)) {
      return;
    }

    const arrival = this.arrival();
    if ('' === arrival || '' !== this.departure() || day.iso <= arrival) {
      this.arrival.set(day.iso);
      this.departure.set('');

      return;
    }

    this.departure.set(day.iso);
    this.close();
  }

  clear(): void {
    this.arrival.set('');
    this.departure.set('');
  }

  isEnd(iso: string): boolean {
    return iso === this.arrival() || iso === this.departure();
  }

  isInside(iso: string): boolean {
    return '' !== this.departure() && iso > this.arrival() && iso < this.departure();
  }

  private month(offset: number): Month {
    const [year, month] = parts(this.today);
    const min = this.min() || this.today;
    const busy = this.busy();
    const first = new Date(year, month - 1 + offset, 1);
    const count = new Date(first.getFullYear(), first.getMonth() + 1, 0).getDate();
    const firstIso = `${first.getFullYear()}-${String(first.getMonth() + 1).padStart(2, '0')}-01`;
    const days: Day[] = [];

    for (let n = 0; n < count; n++) {
      const iso = plusDays(firstIso, n);
      const weekday = new Date(first.getFullYear(), first.getMonth(), n + 1).getDay();
      days.push({
        iso,
        label: n + 1,
        name:
          'fr' === currentLang()
            ? `${DAYS[weekday]} ${n + 1} ${MONTHS[first.getMonth()]} ${first.getFullYear()}`
            : intl(iso, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }),
        saturday: 6 === weekday,
        past: iso < min,
        taken: busy.some((period) => period.start <= iso && iso < period.end),
      });
    }

    return {
      key: firstIso,
      title:
        'fr' === currentLang()
          ? `${MONTHS[first.getMonth()]} ${first.getFullYear()}`
          : intl(firstIso, { month: 'long', year: 'numeric' }),
      // La semaine commence le lundi.
      blanks: Array.from({ length: (first.getDay() + 6) % 7 }, (_, i) => i),
      days,
    };
  }
}
