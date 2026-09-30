import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { catchError, map, Observable, of } from 'rxjs';

import { apiErrorMessage } from '../../core/http/api-error';
import {
  AccommodationStatus,
  OwnerAccommodation,
  STATUS_PARAMS,
  statusFromParam,
  summarize,
} from '../../core/models/owner-accommodation';
import { ListingImportService } from '../../core/services/listing-import';
import { OwnerAccommodationService } from '../../core/services/owner-accommodation';
import { OwnerFlash } from '../../core/services/owner-flash';
import { SeoService } from '../../core/services/seo';
import { OwnerListingCard } from './owner-listing-card/owner-listing-card';
import { FirstListing } from '../../shared/first-listing/first-listing';

/** Les brouillons d'abord : ce sont eux qui attendent une action. */
const STATUS_ORDER: Record<AccommodationStatus, number> = { draft: 0, published: 1, archived: 2 };

const SUCCESS: Record<AccommodationStatus, string> = {
  draft: '',
  published: 'Votre annonce est en ligne.',
  archived: 'Annonce retirée du site. Vous pourrez la remettre en ligne à tout moment.',
};

const EMPTY_FILTER: Record<AccommodationStatus, string> = {
  draft: 'Aucun brouillon : tous vos logements ont déjà été publiés au moins une fois.',
  published: "Aucun logement en ligne pour l'instant.",
  archived: 'Aucun logement retiré du site.',
};

interface Filter {
  /** null : tous les logements. */
  status: AccommodationStatus | null;
  label: string;
  count: number;
}

@Component({
  selector: 'cm-owner-accommodations',
  imports: [RouterLink, OwnerListingCard, FirstListing],
  templateUrl: './owner-accommodations.html',
  styleUrl: './owner-accommodations.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class OwnerAccommodations {
  private readonly service = inject(OwnerAccommodationService);
  private readonly route = inject(ActivatedRoute);

  /** null tant que la liste n'est pas arrivée. */
  readonly accommodations = signal<OwnerAccommodation[] | null>(null);
  readonly loadFailed = signal(false);

  /** Le filtre vient de l'adresse (?statut=en-ligne) : il survit au rechargement. */
  readonly status = toSignal(
    this.route.queryParamMap.pipe(map((params) => statusFromParam(params.get('statut')))),
    { initialValue: null },
  );

  /** Message laissé par le formulaire (« Brouillon enregistré »…), lu une seule fois. */
  private readonly flash = inject(OwnerFlash).take();

  /** Le logement qui vient d'être enregistré : mis en avant d'un halo. */
  readonly highlighted = this.flash?.slug ?? null;

  /**
   * Les logements modifiés sur cette page restent affichés même s'ils ne correspondent
   * plus au filtre : publier un brouillon ne doit pas le faire disparaître sous les yeux.
   */
  private readonly touched = signal<ReadonlySet<string>>(new Set());

  readonly visible = computed(() => {
    const status = this.status();
    const list = this.accommodations() ?? [];

    return null === status
      ? list
      : list.filter((item) => item.status === status || this.touched().has(item.slug));
  });

  readonly filters = computed<Filter[]>(() => {
    const list = this.accommodations() ?? [];
    const count = (status: AccommodationStatus) => list.filter((a) => a.status === status).length;

    return [
      { status: null, label: 'Tous', count: list.length },
      { status: 'published', label: 'En ligne', count: count('published') },
      { status: 'draft', label: 'Brouillons', count: count('draft') },
      { status: 'archived', label: 'Retirés', count: count('archived') },
    ];
  });

  readonly emptyFilterMessage = computed(() => {
    const status = this.status();

    return null === status ? '' : EMPTY_FILTER[status];
  });

  /** Le logement dont une action est en cours : ses boutons sont désactivés. */
  readonly busy = signal<string | null>(null);

  /** Sous chaque carte : le refus de l'API, ou la confirmation d'une action réussie. */
  readonly errors = signal<Record<string, string>>({});
  readonly notices = signal<Record<string, string>>({});

  readonly summary = computed(() => summarize(this.accommodations() ?? []));

  /** Le lien d'import n'apparaît que si l'assistant peut lire tout de suite (ADR 027). */
  readonly assistantAvailable = toSignal(
    inject(ListingImportService)
      .assistant()
      .pipe(
        map((status) => status.available),
        catchError(() => of(false)),
      ),
    { initialValue: false },
  );

  constructor() {
    inject(SeoService).apply({
      title: 'Mes logements',
      description: 'Publiez, modifiez ou retirez vos logements.',
      path: '/mon-espace/logements',
      noindex: true,
    });

    this.service.list().subscribe({
      // Trié une seule fois au chargement : une carte ne saute pas d'un groupe à l'autre
      // pendant qu'on clique dessus.
      next: (list) => {
        this.accommodations.set(
          [...list].sort((a, b) => STATUS_ORDER[a.status] - STATUS_ORDER[b.status]),
        );

        const flash = this.flash;

        if (null !== flash) {
          // Reste visible même s'il ne correspond pas au filtre en cours.
          this.touched.set(new Set([flash.slug]));
          if ('ok' === flash.tone) {
            this.notices.set({ [flash.slug]: flash.message });
          } else {
            this.errors.set({ [flash.slug]: flash.message });
          }
        }
      },
      error: () => this.loadFailed.set(true),
    });
  }

  /** Paramètres d'adresse d'un filtre : null retire le paramètre (tous les logements). */
  filterParams(status: AccommodationStatus | null): { statut: string | null } {
    return { statut: null === status ? null : STATUS_PARAMS[status] };
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
    this.errors.update((all) => without(all, item.slug));
    this.notices.update((all) => without(all, item.slug));

    request.subscribe({
      // L'API renvoie le logement à jour : on remplace la carte, sans recharger la liste.
      next: (updated) => {
        this.accommodations.update(
          (list) => list?.map((one) => (one.slug === updated.slug ? updated : one)) ?? null,
        );
        this.touched.update((slugs) => new Set(slugs).add(updated.slug));
        this.notices.update((all) => ({ ...all, [updated.slug]: SUCCESS[updated.status] }));
        this.busy.set(null);
      },
      error: (error: HttpErrorResponse) => {
        this.errors.update((all) => ({
          ...all,
          [item.slug]: apiErrorMessage(error, "L'opération n'a pas abouti. Réessayez."),
        }));
        this.busy.set(null);
      },
    });
  }
}

function without(record: Record<string, string>, key: string): Record<string, string> {
  const copy = { ...record };
  delete copy[key];
  return copy;
}
