import { DatePipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { Observable } from 'rxjs';

import { apiErrorMessage } from '../../core/http/api-error';
import { typeLabel } from '../../core/models/accommodation';
import {
  byYear,
  nextPeriodDates,
  OwnerRates as Rates,
  RatePeriod,
  toCents,
  toEuros,
} from '../../core/models/owner-rates';
import { OwnerAccommodationService } from '../../core/services/owner-accommodation';
import { OwnerRatesService } from '../../core/services/owner-rates';
import { SeoService } from '../../core/services/seo';

/** Le formulaire d'une période, en euros : ce que le propriétaire tape. */
interface Draft {
  /** null : nouvelle période. */
  id: string | null;
  start: string;
  end: string;
  weekly: number | null;
  nightly: number | null;
  minimumNights: number;
  saturdayArrival: boolean;
}

/**
 * La grille tarifaire d'un logement, saison par saison. Une période = des dates, un prix à la
 * semaine et/ou à la nuit, un minimum de nuits (bloquant) et la préférence du samedi
 * (indicative). « Reprendre les tarifs de l'an dernier » recopie une saison en un clic.
 */
@Component({
  selector: 'cm-owner-rates',
  imports: [DatePipe, FormsModule, RouterLink],
  templateUrl: './owner-rates.html',
  styleUrl: './owner-rates.scss',
})
export class OwnerRates {
  private readonly service = inject(OwnerRatesService);

  readonly slug = inject(ActivatedRoute).snapshot.paramMap.get('slug') ?? '';

  readonly rates = signal<Rates | null>(null);
  readonly title = signal('');
  readonly loadFailed = signal(false);
  readonly draft = signal<Draft | null>(null);
  readonly saving = signal(false);
  readonly error = signal<string | null>(null);
  readonly notice = signal<string | null>(null);
  readonly confirmDelete = signal<string | null>(null);
  readonly showPast = signal(false);

  readonly upcoming = computed(() => byYear((this.rates()?.periods ?? []).filter((p) => !p.past)));
  readonly past = computed(() => (this.rates()?.periods ?? []).filter((p) => p.past));

  /** La copie n'a de sens que si l'année cible n'a encore aucun tarif. */
  readonly copy = computed(() => this.rates()?.copySuggestion ?? null);

  readonly toEuros = toEuros;

  constructor() {
    inject(SeoService).apply({
      title: 'Tarifs',
      description: 'Vos tarifs par période.',
      path: '/mon-espace/logements',
      noindex: true,
    });

    inject(OwnerAccommodationService)
      .get(this.slug)
      .subscribe({
        next: (item) =>
          this.title.set(
            `${typeLabel(item.type)} · ${item.district ?? ('chm' === item.resort ? 'CHM Montalivet' : 'Euronat')}`,
          ),
        error: () => undefined,
      });

    this.service.get(this.slug).subscribe({
      next: (rates) => this.rates.set(rates),
      error: () => this.loadFailed.set(true),
    });
  }

  add(): void {
    const rates = this.rates();

    if (null === rates) {
      return;
    }

    const dates = nextPeriodDates(rates.periods, rates.today);
    this.open({
      id: null,
      ...dates,
      weekly: null,
      nightly: null,
      minimumNights: 1,
      saturdayArrival: false,
    });
  }

  edit(period: RatePeriod): void {
    this.open({
      id: period.id,
      start: period.start,
      end: period.end,
      weekly: toEuros(period.weeklyPrice),
      nightly: toEuros(period.nightlyPrice),
      minimumNights: period.minimumNights,
      saturdayArrival: period.saturdayArrival,
    });
  }

  patch(changes: Partial<Draft>): void {
    this.draft.update((draft) => (null === draft ? null : { ...draft, ...changes }));
  }

  cancel(): void {
    this.draft.set(null);
    this.error.set(null);
  }

  save(): void {
    const draft = this.draft();

    if (null === draft) {
      return;
    }

    const input = {
      start: draft.start,
      end: draft.end,
      weeklyPrice: toCents(draft.weekly),
      nightlyPrice: toCents(draft.nightly),
      minimumNights: Number(draft.minimumNights),
      saturdayArrival: draft.saturdayArrival,
    };

    // Rien à envoyer : on le dit ici plutôt que d'attendre le refus de l'API.
    if (null === input.weeklyPrice && null === input.nightlyPrice) {
      this.error.set('Indiquez au moins un prix : à la semaine ou à la nuit.');

      return;
    }

    const request =
      null === draft.id
        ? this.service.create(this.slug, input)
        : this.service.update(this.slug, draft.id, input);

    this.run(request, () => {
      this.draft.set(null);
      this.notice.set(
        null === draft.id
          ? 'Période ajoutée : elle apparaît déjà sur votre annonce.'
          : 'Période modifiée.',
      );
    });
  }

  remove(period: RatePeriod): void {
    this.run(this.service.remove(this.slug, period.id), () => {
      this.confirmDelete.set(null);
      this.notice.set(
        'Période supprimée. Sur ces dates, les demandes arriveront avec un prix « à convenir ».',
      );
    });
  }

  copyYear(from: number, to: number): void {
    this.run(this.service.copy(this.slug, from), (rates) => {
      const copied = rates.copied ?? 0;
      const skipped = rates.skipped ?? 0;
      this.notice.set(
        0 === copied
          ? `Rien à recopier : les dates de ${to} sont déjà couvertes.`
          : `${copied} période${copied > 1 ? 's' : ''} de ${from} recopiée${copied > 1 ? 's' : ''} sur ${to}, aux mêmes jours de la semaine. Ajustez les prix si besoin.` +
              (skipped
                ? ` ${skipped} ignorée${skipped > 1 ? 's' : ''} (déjà couverte${skipped > 1 ? 's' : ''} ou passée${skipped > 1 ? 's' : ''}).`
                : ''),
      );
    });
  }

  private open(draft: Draft): void {
    this.draft.set(draft);
    this.error.set(null);
    this.notice.set(null);
    this.confirmDelete.set(null);
  }

  private run(request: Observable<Rates>, done: (rates: Rates) => void): void {
    this.saving.set(true);
    this.error.set(null);

    request.subscribe({
      next: (rates) => {
        this.rates.set(rates);
        this.saving.set(false);
        done(rates);
      },
      error: (error: HttpErrorResponse) => {
        this.saving.set(false);
        this.error.set(apiErrorMessage(error, 'L’enregistrement a échoué. Réessayez.'));
      },
    });
  }
}
