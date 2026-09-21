import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { Observable } from 'rxjs';

import { apiErrorMessage } from '../../core/http/api-error';
import { typeLabel } from '../../core/models/accommodation';
import { OwnerAccommodation, statusLabel } from '../../core/models/owner-accommodation';
import { OwnerAccommodationService } from '../../core/services/owner-accommodation';
import { SeoService } from '../../core/services/seo';

@Component({
  selector: 'cm-owner-accommodations',
  imports: [RouterLink],
  templateUrl: './owner-accommodations.html',
  styleUrl: './owner-accommodations.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class OwnerAccommodations {
  private readonly service = inject(OwnerAccommodationService);

  /** null tant que la liste n'est pas arrivée. */
  readonly accommodations = signal<OwnerAccommodation[] | null>(null);
  readonly loadFailed = signal(false);

  /** Le logement dont une action est en cours : ses boutons sont désactivés. */
  readonly busy = signal<string | null>(null);

  /** Le refus de l'API, affiché sous la carte concernée. */
  readonly errors = signal<Record<string, string>>({});

  readonly statusLabel = statusLabel;
  readonly typeLabel = typeLabel;

  constructor() {
    inject(SeoService).apply({
      title: 'Mes logements',
      description: 'Publiez, modifiez ou retirez vos logements.',
      path: '/mon-espace/logements',
      noindex: true,
    });

    this.service.list().subscribe({
      next: (list) => this.accommodations.set(list),
      error: () => this.loadFailed.set(true),
    });
  }

  place(item: OwnerAccommodation): string {
    return item.district ?? ('chm' === item.resort ? 'CHM Montalivet' : 'Euronat');
  }

  publish(item: OwnerAccommodation): void {
    this.run(item, this.service.publish(item.slug));
  }

  archive(item: OwnerAccommodation): void {
    this.run(item, this.service.archive(item.slug));
  }

  private run(item: OwnerAccommodation, request: Observable<OwnerAccommodation>): void {
    if (null !== this.busy()) {
      return;
    }

    this.busy.set(item.slug);
    this.errors.update((errors) => {
      const next = { ...errors };
      delete next[item.slug];
      return next;
    });

    request.subscribe({
      // L'API renvoie le logement à jour : on remplace la carte, sans recharger la liste.
      next: (updated) => {
        this.accommodations.update(
          (list) => list?.map((one) => (one.slug === updated.slug ? updated : one)) ?? null,
        );
        this.busy.set(null);
      },
      error: (error: HttpErrorResponse) => {
        this.errors.update((errors) => ({
          ...errors,
          [item.slug]: apiErrorMessage(error, "L'opération n'a pas abouti. Réessayez."),
        }));
        this.busy.set(null);
      },
    });
  }
}
