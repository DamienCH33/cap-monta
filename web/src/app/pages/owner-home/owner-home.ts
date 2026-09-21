import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';

import {
  AccommodationStatus,
  OwnerAccommodation,
  STATUS_PARAMS,
} from '../../core/models/owner-accommodation';
import { AuthService } from '../../core/services/auth';
import { OwnerAccommodationService } from '../../core/services/owner-accommodation';
import { SeoService } from '../../core/services/seo';

interface Stat {
  status: AccommodationStatus;
  /** Filtre de la liste, dans l'adresse : ?statut=en-ligne */
  param: string;
  label: string;
  count: number | null;
}

@Component({
  selector: 'cm-owner-home',
  imports: [RouterLink],
  templateUrl: './owner-home.html',
  styleUrl: './owner-home.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class OwnerHome {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  readonly owner = this.auth.currentOwner;

  /** null tant que la liste n'est pas arrivée. */
  private readonly accommodations = signal<OwnerAccommodation[] | null>(null);

  /** Les trois chiffres du tableau de bord ; null pendant le chargement. */
  readonly stats = computed<Stat[]>(() => {
    const list = this.accommodations();
    const count = (status: AccommodationStatus) =>
      null === list ? null : list.filter((a) => a.status === status).length;

    return [
      { status: 'published', param: STATUS_PARAMS.published, label: 'En ligne', count: count('published') },
      { status: 'draft', param: STATUS_PARAMS.draft, label: 'Brouillons', count: count('draft') },
      { status: 'archived', param: STATUS_PARAMS.archived, label: 'Retirés du site', count: count('archived') },
    ];
  });

  /** Un brouillon est une action en attente : on la signale dès l'arrivée. */
  readonly draftNotice = computed(() => {
    const drafts = (this.accommodations() ?? []).filter((a) => 'draft' === a.status).length;

    if (0 === drafts) {
      return null;
    }

    return drafts > 1
      ? `${drafts} brouillons attendent d'être publiés.`
      : "1 brouillon attend d'être publié.";
  });

  constructor() {
    inject(SeoService).apply({
      title: 'Mon espace',
      description: 'Gérez vos logements, votre calendrier et vos demandes.',
      path: '/mon-espace',
      noindex: true,
    });

    inject(OwnerAccommodationService)
      .list()
      .subscribe({
        next: (list) => this.accommodations.set(list),
        // Les chiffres restent à « – » : le lien vers la liste fonctionne quand même.
        error: () => undefined,
      });
  }

  logout(): void {
    this.auth.logout().subscribe(() => void this.router.navigateByUrl('/'));
  }
}
