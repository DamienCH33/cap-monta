import { DatePipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';

import { apiErrorMessage } from '../../core/http/api-error';
import { StayAlertService, StayAlertView } from '../../core/services/stay-alert';
import { SeoService } from '../../core/services/seo';

/**
 * La page d'une alerte « Prévenez-moi », ouverte depuis ses emails : /alerte/<clé>/activer
 * l'active, /alerte/<clé> la montre. Les deux permettent de l'arrêter.
 */
@Component({
  selector: 'cm-stay-alert',
  imports: [DatePipe, RouterLink],
  templateUrl: './stay-alert.html',
  styleUrl: './stay-alert.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class StayAlertPage {
  private readonly alerts = inject(StayAlertService);
  private readonly route = inject(ActivatedRoute).snapshot;
  private readonly key = this.route.paramMap.get('token') ?? '';

  readonly alert = signal<StayAlertView | null>(null);
  readonly notFound = signal(false);
  readonly stopped = signal(false);
  readonly saving = signal(false);
  readonly error = signal<string | null>(null);

  constructor() {
    inject(SeoService).apply({
      title: $localize`:@@seo.alert.title:Votre alerte`,
      description: $localize`:@@seo.alert.description:Soyez prévenu quand un logement se libère à vos dates.`,
      path: '/recherche',
      noindex: true,
    });

    const activate = this.route.url.some((segment) => 'activer' === segment.path);
    const load = activate ? this.alerts.confirm(this.key) : this.alerts.show(this.key);

    load.subscribe({
      next: (alert) => this.alert.set(alert),
      error: () => this.notFound.set(true),
    });
  }

  /** Les paramètres de la recherche, pour revoir les logements libres à ces dates. */
  searchParams(alert: StayAlertView): Record<string, string | number> {
    return {
      arrivee: alert.arrival,
      depart: alert.departure,
      adultes: alert.guests,
      ...(alert.resort ? { domaine: alert.resort } : {}),
    };
  }

  stop(): void {
    this.saving.set(true);
    this.error.set(null);

    this.alerts.stop(this.key).subscribe({
      next: () => {
        this.saving.set(false);
        this.stopped.set(true);
      },
      error: (error: HttpErrorResponse) => {
        this.saving.set(false);
        this.error.set(
          apiErrorMessage(
            error,
            $localize`:@@common.send-failed:L'envoi a échoué. Réessayez dans un instant.`,
          ),
        );
      },
    });
  }
}
