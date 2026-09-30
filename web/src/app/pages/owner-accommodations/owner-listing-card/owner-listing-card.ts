import { ChangeDetectionStrategy, Component, computed, inject, input, output } from '@angular/core';
import { RouterLink } from '@angular/router';

import { typeLabel } from '../../../core/models/accommodation';
import {
  AccommodationStatus,
  OwnerAccommodation,
  plural,
  statusLabel,
} from '../../../core/models/owner-accommodation';
import { NavigationOrigin } from '../../../core/services/navigation-origin';
import { Icon } from '../../../shared/icon/icon';
import { ListingShare } from '../listing-share/listing-share';

const HINTS: Record<AccommodationStatus, string> = {
  draft: "Visible uniquement par vous. Publiez-le pour qu'il apparaisse dans les recherches.",
  published: 'Visible par tous les visiteurs du site.',
  archived: 'Retiré du site. Son adresse et ses demandes passées sont conservées.',
};

/**
 * Un logement dans « Mes logements ». Trois étages : la photo et l'identité du logement
 * (trois « Bungalow · Europa » se distinguent d'abord par leur photo), l'action principale
 * à droite, puis les outils du quotidien (calendrier, tarifs, fiche, partage) en boutons
 * icône + libellé. « Retirer du site », rare et lourd de conséquences, passe en retrait.
 */
@Component({
  selector: 'cm-owner-listing-card',
  imports: [RouterLink, Icon, ListingShare],
  templateUrl: './owner-listing-card.html',
  styleUrl: './owner-listing-card.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: {
    '[class]': "'card card--' + item().status",
    '[class.card--new]': 'highlighted()',
  },
})
export class OwnerListingCard {
  readonly item = input.required<OwnerAccommodation>();
  readonly highlighted = input(false);
  /** Le logement dont une action est en cours sur la page (null : aucun). */
  readonly busy = input<string | null>(null);
  readonly error = input<string | undefined>();
  readonly notice = input<string | undefined>();

  readonly publish = output<void>();
  readonly archive = output<void>();

  /** « Voir l'annonce » le note : le lien de retour de la fiche ramènera ici. */
  readonly origin = inject(NavigationOrigin);

  readonly title = computed(() => `${typeLabel(this.item().type)} · ${this.place()}`);
  readonly status = computed(() => statusLabel(this.item().status));
  readonly hint = computed(() => HINTS[this.item().status]);
  readonly cover = computed(() => this.item().photos[0]?.thumbUrl ?? null);
  readonly working = computed(() => this.busy() === this.item().slug);
  readonly locked = computed(() => null !== this.busy());

  readonly details = computed(() => {
    const item = this.item();
    const parts = [
      plural(item.capacity, 'personne', 'personnes'),
      plural(item.bedrooms, 'chambre', 'chambres'),
    ];

    if (item.surface) {
      parts.push(`${item.surface} m²`);
    }

    return parts.join(' · ');
  });

  private place(): string {
    const item = this.item();

    return item.district ?? ('chm' === item.resort ? 'CHM Montalivet' : 'Euronat');
  }
}
