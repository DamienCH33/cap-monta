import { DatePipe } from '@angular/common';
import { Component, computed, input, signal } from '@angular/core';

import { BusyPeriod } from '../../core/models/availability';
import { Icon } from '../icon/icon';

interface Day {
  key: string;
  label: number;
  busy: boolean;
  past: boolean;
}

interface Month {
  key: string;
  first: Date;
  blanks: number[];
  days: Day[];
}

/** "2026-07-04" à partir d'une date locale, sans passer par UTC. */
function isoDay(date: Date): string {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');

  return `${year}-${month}-${day}`;
}

@Component({
  selector: 'cm-calendar',
  imports: [Icon, DatePipe],
  templateUrl: './calendar.html',
  styleUrl: './calendar.scss',
})
export class Calendar {
  readonly busy = input<BusyPeriod[]>([]);
  readonly totalMonths = input(12);
  readonly visibleMonths = input(2);

  readonly weekdays = ['L', 'M', 'M', 'J', 'V', 'S', 'D'];

  /** Premier mois affiché, en nombre de mois depuis le mois courant. */
  private readonly offset = signal(0);

  /** Tous les jours occupés, sous forme "2026-07-04", bornes [) comprises. */
  private readonly busyDays = computed(() => {
    const days = new Set<string>();

    for (const period of this.busy()) {
      const start = new Date(`${period.start.slice(0, 10)}T00:00:00`);
      const end = new Date(`${period.end.slice(0, 10)}T00:00:00`);

      for (const day = new Date(start); day < end; day.setDate(day.getDate() + 1)) {
        days.add(isoDay(day));
      }
    }

    return days;
  });

  /** Les douze mois, calculés une fois. */
  private readonly allMonths = computed<Month[]>(() => {
    const busyDays = this.busyDays();
    const today = isoDay(new Date());
    const now = new Date();
    const months: Month[] = [];

    for (let index = 0; index < this.totalMonths(); index++) {
      const first = new Date(now.getFullYear(), now.getMonth() + index, 1);
      const dayCount = new Date(first.getFullYear(), first.getMonth() + 1, 0).getDate();

      // getDay() renvoie 0 pour dimanche ; on décale pour une semaine qui commence le lundi.
      const blanks = (first.getDay() + 6) % 7;

      const days: Day[] = [];

      for (let number = 1; number <= dayCount; number++) {
        const key = isoDay(new Date(first.getFullYear(), first.getMonth(), number));

        days.push({
          key,
          label: number,
          busy: busyDays.has(key),
          past: key < today,
        });
      }

      months.push({
        key: isoDay(first),
        first,
        blanks: Array.from({ length: blanks }, (_, i) => i),
        days,
      });
    }

    return months;
  });

  /** La fenêtre visible, deux mois par défaut. */
  readonly months = computed(() =>
    this.allMonths().slice(this.offset(), this.offset() + this.visibleMonths()),
  );

  readonly canGoBack = computed(() => this.offset() > 0);

  readonly canGoForward = computed(() => this.offset() + this.visibleMonths() < this.totalMonths());

  previous(): void {
    this.offset.update((current) => Math.max(0, current - 1));
  }

  next(): void {
    this.offset.update((current) =>
      Math.min(this.totalMonths() - this.visibleMonths(), current + 1),
    );
  }
}
