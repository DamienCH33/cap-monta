import { DatePipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';

import { apiErrorMessage } from '../../core/http/api-error';
import { typeLabel } from '../../core/models/accommodation';
import {
  DayRange,
  isoDay,
  lastDay,
  localDate,
  OwnerCalendar as Calendar,
  OwnerPeriod,
  PeriodSource,
  dayCount,
  overlapsAny,
  rangeBetween,
  saturdayWeek,
  sourceLabel,
} from '../../core/models/owner-calendar';
import { OwnerAccommodationService } from '../../core/services/owner-accommodation';
import { OwnerCalendarService } from '../../core/services/owner-calendar';
import { SeoService } from '../../core/services/seo';

interface Day {
  key: string;
  label: number;
  past: boolean;
  period: OwnerPeriod | null;
}

interface Month {
  key: string;
  first: Date;
  blanks: number[];
  days: Day[];
}

/** Douze mois d'abord ; les six suivants sur demande (l'API accepte 18 mois). */
const MONTHS = 12;
const MORE_MONTHS = 18;

/**
 * Le calendrier d'un logement, côté propriétaire.
 *
 * Un clic choisit un jour ; un second clic sur un autre jour étend la sélection jusqu'à
 * lui. Le raccourci « toute la semaine » prend du samedi au samedi. Un clic sur une
 * période déjà prise l'affiche, avec sa note et, si c'est un blocage, de quoi le retirer.
 */
@Component({
  selector: 'cm-owner-calendar',
  imports: [DatePipe, FormsModule, RouterLink],
  templateUrl: './owner-calendar.html',
  styleUrl: './owner-calendar.scss',
})
export class OwnerCalendar {
  private readonly service = inject(OwnerCalendarService);

  readonly slug = inject(ActivatedRoute).snapshot.paramMap.get('slug') ?? '';
  readonly weekdays = ['L', 'M', 'M', 'J', 'V', 'S', 'D'];
  readonly sourceLabel = sourceLabel;
  readonly lastDay = lastDay;
  readonly dayCount = dayCount;

  readonly calendar = signal<Calendar | null>(null);
  readonly title = signal('Calendrier');
  readonly loadFailed = signal(false);
  readonly monthCount = signal(MONTHS);

  /** Premier jour cliqué, en attente d'un second clic pour étendre la sélection. */
  readonly anchor = signal<string | null>(null);
  readonly hovered = signal<string | null>(null);
  readonly selection = signal<DayRange | null>(null);
  readonly selectedPeriod = signal<OwnerPeriod | null>(null);

  readonly note = signal('');
  readonly saving = signal(false);
  readonly error = signal<string | null>(null);
  readonly notice = signal<string | null>(null);

  readonly canShowMore = computed(() => this.monthCount() < MORE_MONTHS);

  /** Le jour → la période qui le couvre. Calculé une fois par version du calendrier. */
  private readonly periodByDay = computed(() => {
    const days = new Map<string, OwnerPeriod>();

    for (const period of this.calendar()?.periods ?? []) {
      for (
        let day = localDate(period.start);
        isoDay(day) < period.end;
        day.setDate(day.getDate() + 1)
      ) {
        days.set(isoDay(day), period);
      }
    }

    return days;
  });

  readonly months = computed<Month[]>(() => {
    const periods = this.periodByDay();
    const today = this.calendar()?.from ?? isoDay(new Date());
    const start = localDate(today);
    const months: Month[] = [];

    for (let index = 0; index < this.monthCount(); index++) {
      const first = new Date(start.getFullYear(), start.getMonth() + index, 1);
      const count = new Date(first.getFullYear(), first.getMonth() + 1, 0).getDate();
      const days: Day[] = [];

      for (let number = 1; number <= count; number++) {
        const key = isoDay(new Date(first.getFullYear(), first.getMonth(), number));
        days.push({ key, label: number, past: key < today, period: periods.get(key) ?? null });
      }

      months.push({
        key: isoDay(first),
        first,
        // Semaine du lundi : getDay() vaut 0 le dimanche.
        blanks: Array.from({ length: (first.getDay() + 6) % 7 }, (_, i) => i),
        days,
      });
    }

    return months;
  });

  /** Ce qui est surligné : la sélection, ou l'aperçu entre le premier clic et le jour survolé. */
  readonly highlighted = computed<DayRange | null>(() => {
    const anchor = this.anchor();
    const hovered = this.hovered();

    return null !== anchor && null !== hovered ? rangeBetween(anchor, hovered) : this.selection();
  });

  readonly selectionConflicts = computed(() => {
    const selection = this.selection();

    return null !== selection && overlapsAny(selection, this.calendar()?.periods ?? []);
  });

  /** Proposé tant qu'un seul jour est choisi. */
  readonly week = computed(() => {
    const anchor = this.anchor();

    return null === anchor ? null : saturdayWeek(anchor);
  });

  readonly upcoming = computed(() => this.calendar()?.periods ?? []);

  constructor() {
    const accommodations = inject(OwnerAccommodationService);

    inject(SeoService).apply({
      title: 'Calendrier',
      description: 'Bloquez les dates où votre logement n’est pas disponible.',
      path: '/mon-espace/logements',
      noindex: true,
    });

    accommodations.get(this.slug).subscribe({
      next: (item) =>
        this.title.set(
          `${typeLabel(item.type)} · ${item.district ?? ('chm' === item.resort ? 'CHM Montalivet' : 'Euronat')}`,
        ),
      error: () => undefined,
    });

    this.service.get(this.slug).subscribe({
      next: (calendar) => this.calendar.set(calendar),
      error: () => this.loadFailed.set(true),
    });
  }

  isHighlighted(day: string): boolean {
    const range = this.highlighted();

    return null !== range && day >= range.start && day < range.end;
  }

  sourceClass(source: PeriodSource): string {
    return 'booking' === source ? 'booking' : 'block' === source ? 'block' : 'import';
  }

  dayLabel(day: Day): string {
    const date = localDate(day.key).toLocaleDateString('fr-FR', {
      weekday: 'long',
      day: 'numeric',
      month: 'long',
    });

    return `${date}, ${null === day.period ? 'libre' : sourceLabel(day.period.source).toLowerCase()}`;
  }

  pick(day: Day): void {
    if (day.past || this.saving()) {
      return;
    }

    this.error.set(null);
    this.notice.set(null);

    if (null !== day.period) {
      this.clearSelection();
      this.selectedPeriod.set(day.period);

      return;
    }

    this.selectedPeriod.set(null);
    const anchor = this.anchor();

    if (null === anchor) {
      // Premier clic : un jour, déjà bloquable tel quel.
      this.anchor.set(day.key);
      this.selection.set(rangeBetween(day.key, day.key));
    } else {
      this.selection.set(rangeBetween(anchor, day.key));
      this.anchor.set(null);
      this.hovered.set(null);
    }
  }

  /** Aperçu de la plage à la souris seulement : au doigt, il n'y a pas de survol, juste des clics. */
  hover(day: Day | null, event?: PointerEvent): void {
    if (undefined !== event && 'mouse' !== event.pointerType) {
      return;
    }

    if (null !== this.anchor()) {
      this.hovered.set(null === day || day.past ? null : day.key);
    }
  }

  pickWeek(): void {
    const week = this.week();

    if (null !== week) {
      this.selection.set(week);
      this.anchor.set(null);
      this.hovered.set(null);
    }
  }

  clearSelection(): void {
    this.anchor.set(null);
    this.hovered.set(null);
    this.selection.set(null);
    this.note.set('');
  }

  block(): void {
    const selection = this.selection();

    if (null === selection || this.selectionConflicts()) {
      return;
    }

    this.run(this.service.block(this.slug, selection, this.note()), () => {
      this.clearSelection();
      this.notice.set('Dates bloquées. Votre annonce est à jour.');
    });
  }

  unblock(period: OwnerPeriod): void {
    this.run(this.service.unblock(this.slug, period.id), () => {
      this.selectedPeriod.set(null);
      this.notice.set('Ces dates sont de nouveau libres.');
    });
  }

  confirm(): void {
    this.run(this.service.confirm(this.slug), () =>
      this.notice.set('Merci : le badge « Calendrier à jour » s’affiche sur votre annonce.'),
    );
  }

  showMore(): void {
    this.monthCount.set(MORE_MONTHS);
  }

  private run(request: ReturnType<OwnerCalendarService['get']>, done: () => void): void {
    this.saving.set(true);
    this.error.set(null);

    request.subscribe({
      next: (calendar) => {
        this.calendar.set(calendar);
        this.saving.set(false);
        done();
      },
      error: (error: HttpErrorResponse) => {
        this.saving.set(false);
        this.error.set(apiErrorMessage(error, 'L’enregistrement a échoué. Réessayez.'));
      },
    });
  }
}
