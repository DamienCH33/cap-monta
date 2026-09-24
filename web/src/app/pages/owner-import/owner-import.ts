import { HttpErrorResponse } from '@angular/common/http';
import { Component, computed, DestroyRef, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { Subscription } from 'rxjs';

import { PETS_POLICIES, typeLabel } from '../../core/models/accommodation';
import {
  AssistantStatus,
  ListingImportView,
  otherErrors,
  rangeRows,
  RangeRow,
  rangesPayload,
  RateRow,
  rateRows,
  ratesPayload,
  rowErrors,
  Violation,
} from '../../core/models/listing-import';
import { DistrictOption } from '../../core/models/owner-accommodation';
import { AMENITIES, AMENITY_GROUPS } from '../../core/models/search-filters';
import { ApplyPayload, ListingImportService } from '../../core/services/listing-import';
import { OwnerAccommodationService } from '../../core/services/owner-accommodation';
import { OwnerFlash } from '../../core/services/owner-flash';
import { SeoService } from '../../core/services/seo';

type Step = 'paste' | 'reading' | 'check' | 'failed';

/** Le formulaire du logement, pré-rempli par la lecture. */
interface ListingDraft {
  resort: string;
  type: string;
  capacity: number | null;
  bedrooms: number | null;
  surface: number | null;
  district: string;
  amenities: string[];
  petsPolicy: string;
  description: string;
}

export const MIN_TEXT = 30;
export const MAX_TEXT = 12000;

/**
 * « Importer depuis mon annonce » : le propriétaire colle le texte de l'annonce qu'il publie
 * déjà ailleurs, l'assistant le lit (dans le worker, ADR 027), puis il vérifie tout avant que
 * quoi que ce soit ne soit enregistré. Rien n'est publié ici : un nouveau logement part en
 * brouillon, à compléter avec ses photos.
 *
 * ?logement=slug : remplir un logement existant (tarifs et dates prises seulement).
 * ?lecture=id    : reprendre une lecture (après un rechargement, ou en revenant plus tard).
 */
@Component({
  selector: 'cm-owner-import',
  imports: [FormsModule, RouterLink],
  templateUrl: './owner-import.html',
  styleUrl: './owner-import.scss',
})
export class OwnerImport {
  private readonly service = inject(ListingImportService);
  private readonly accommodations = inject(OwnerAccommodationService);
  private readonly router = inject(Router);
  private readonly flash = inject(OwnerFlash);
  private readonly destroyRef = inject(DestroyRef);
  private polling: Subscription | null = null;

  private readonly params = inject(ActivatedRoute).snapshot.queryParamMap;
  /** Le logement à remplir ; null : un nouveau brouillon. */
  readonly target = this.params.get('logement');

  readonly step = signal<Step>('paste');
  readonly assistant = signal<AssistantStatus | null>(null);
  readonly targetTitle = signal<string | null>(null);
  readonly districts = signal<DistrictOption[]>([]);

  readonly text = signal('');
  readonly sending = signal(false);
  readonly error = signal<string | null>(null);
  readonly view = signal<ListingImportView | null>(null);

  readonly listing = signal<ListingDraft | null>(null);
  readonly rates = signal<RateRow[]>([]);
  readonly ranges = signal<RangeRow[]>([]);
  readonly violations = signal<Violation[]>([]);
  readonly saving = signal(false);
  /** Déjà enregistrée : le logement à ouvrir. */
  readonly appliedTo = signal<string | null>(null);

  readonly minText = MIN_TEXT;
  readonly maxText = MAX_TEXT;
  readonly types = [
    { value: 'mobile_home', label: 'Mobil-home' },
    { value: 'bungalow', label: 'Bungalow' },
    { value: 'caravan', label: 'Caravane' },
    { value: 'chalet', label: 'Chalet' },
    { value: 'studio', label: 'Studio' },
  ];
  readonly petsPolicies = PETS_POLICIES;
  readonly amenityGroups = AMENITY_GROUPS.map((group) => ({
    ...group,
    options: AMENITIES.filter((amenity) => amenity.group === group.key),
  }));

  readonly length = computed(() => this.text().trim().length);
  readonly canSend = computed(
    () => this.length() >= MIN_TEXT && this.length() <= MAX_TEXT && !this.sending(),
  );
  readonly proposal = computed(() => this.view()?.proposal ?? null);
  readonly resortDistricts = computed(() =>
    this.districts().filter((district) => district.resort === this.listing()?.resort),
  );
  readonly rateErrors = computed(() => rowErrors(this.violations(), 'periods', this.rates()));
  readonly rangeErrors = computed(() => rowErrors(this.violations(), 'unavailable', this.ranges()));
  readonly generalErrors = computed(() => otherErrors(this.violations()));
  readonly keptRates = computed(() => this.rates().filter((row) => row.keep).length);
  readonly keptRanges = computed(() => this.ranges().filter((row) => row.keep).length);

  constructor() {
    inject(SeoService).apply({
      title: 'Importer mon annonce',
      description: 'Remplissez votre annonce à partir du texte que vous publiez déjà.',
      path: '/mon-espace/importer',
      noindex: true,
    });

    this.service.assistant().subscribe({
      next: (status) => this.assistant.set(status),
      error: () => this.assistant.set({ available: false, reason: null, until: null }),
    });
    this.accommodations.districts().subscribe({
      next: (list) => this.districts.set(list),
      error: () => this.districts.set([]),
    });

    if (null !== this.target) {
      this.accommodations.get(this.target).subscribe({
        next: (item) =>
          this.targetTitle.set(`${typeLabel(item.type)} · ${item.district ?? 'Euronat'}`),
        error: () => this.error.set('Ce logement est introuvable dans votre espace.'),
      });
    }

    const reading = this.params.get('lecture');
    if (null !== reading) {
      this.step.set('reading');
      this.follow(reading);
    }
  }

  read(): void {
    if (!this.canSend()) {
      return;
    }

    this.sending.set(true);
    this.error.set(null);
    this.service.create(this.text().trim()).subscribe({
      next: (view) => {
        this.sending.set(false);
        // L'adresse garde la lecture : un rechargement ou un retour plus tard la retrouve.
        void this.router.navigate([], {
          queryParams: { lecture: view.id },
          queryParamsHandling: 'merge',
          replaceUrl: true,
        });
        this.show(view);
        if ('pending' === view.status) {
          this.follow(view.id);
        }
      },
      error: (error: HttpErrorResponse) => {
        this.sending.set(false);
        this.error.set(errorMessage(error, 'L’envoi a échoué. Réessayez.'));
      },
    });
  }

  /** Recommencer avec un autre texte : la lecture en cours n'est pas perdue, elle reste en base. */
  restart(): void {
    this.polling?.unsubscribe();
    this.view.set(null);
    this.violations.set([]);
    this.error.set(null);
    this.step.set('paste');
    void this.router.navigate([], {
      queryParams: { lecture: null },
      queryParamsHandling: 'merge',
      replaceUrl: true,
    });
  }

  patchListing(changes: Partial<ListingDraft>): void {
    this.listing.update((draft) => (null === draft ? null : { ...draft, ...changes }));
  }

  toggleAmenity(key: string, checked: boolean): void {
    const current = this.listing()?.amenities ?? [];
    this.patchListing({
      amenities: checked ? [...new Set([...current, key])] : current.filter((a) => a !== key),
    });
  }

  patchRate(index: number, changes: Partial<RateRow>): void {
    this.rates.update((rows) => rows.map((row, i) => (i === index ? { ...row, ...changes } : row)));
  }

  patchRange(index: number, changes: Partial<RangeRow>): void {
    this.ranges.update((rows) =>
      rows.map((row, i) => (i === index ? { ...row, ...changes } : row)),
    );
  }

  save(): void {
    const view = this.view();
    const listing = this.listing();

    if (null === view || null === listing) {
      return;
    }

    const payload: ApplyPayload = {
      periods: ratesPayload(this.rates()),
      unavailable: rangesPayload(this.ranges()),
    };
    if (null !== this.target) {
      payload.slug = this.target;
    } else {
      payload.accommodation = {
        ...listing,
        district: '' === listing.district ? null : listing.district,
      };
    }

    this.saving.set(true);
    this.violations.set([]);
    this.error.set(null);

    this.service.apply(view.id, payload).subscribe({
      next: ({ slug, created }) => {
        this.saving.set(false);
        this.flash.set({
          slug,
          tone: 'ok',
          message: created
            ? 'Brouillon créé à partir de votre annonce. Ajoutez vos photos, relisez, puis publiez.'
            : 'Tarifs et dates prises ajoutés depuis votre annonce.',
        });
        void this.router.navigate(
          created
            ? ['/mon-espace/logements', slug, 'modifier']
            : ['/mon-espace/logements', slug, 'tarifs'],
        );
      },
      error: (error: HttpErrorResponse) => {
        this.saving.set(false);
        const body = error.error as { violations?: Violation[]; slug?: string } | null;
        if (409 === error.status && body?.slug) {
          this.appliedTo.set(body.slug);
        }
        this.violations.set(body?.violations ?? []);
        this.error.set(errorMessage(error, 'L’enregistrement a échoué. Réessayez.'));
      },
    });
  }

  /** L'adresse du logement déjà rempli par cette lecture. */
  appliedLink(slug: string): string[] {
    return ['/mon-espace/logements', slug, 'modifier'];
  }

  private follow(id: string): void {
    this.polling?.unsubscribe();
    this.polling = this.service
      .watch(id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (view) => this.show(view),
        error: () => {
          this.step.set('paste');
          this.error.set('Cette lecture est introuvable. Collez de nouveau votre annonce.');
        },
      });
  }

  private show(view: ListingImportView): void {
    this.view.set(view);
    this.text.set(view.text);
    this.appliedTo.set(view.appliedTo);

    if ('pending' === view.status) {
      this.step.set('reading');
    } else if ('failed' === view.status || null === view.proposal) {
      this.step.set('failed');
    } else if (this.step() !== 'check') {
      this.fill(view);
      this.step.set('check');
    }
  }

  private fill(view: ListingImportView): void {
    const proposal = view.proposal;
    if (null === proposal) {
      return;
    }

    const listing = proposal.listing;
    this.listing.set({
      resort: listing.resort ?? '',
      type: listing.type ?? '',
      capacity: listing.capacity,
      bedrooms: listing.bedrooms,
      surface: listing.surface,
      district: listing.district ?? '',
      amenities: listing.amenities,
      // Pas écrit dans l'annonce : la règle par défaut du site, que le propriétaire peut changer.
      petsPolicy: listing.petsPolicy ?? 'on_request',
      description: listing.description,
    });
    this.rates.set(rateRows(proposal.periods));
    this.ranges.set(rangeRows(proposal.unavailable));
  }
}

/** Les refus de cette API portent « message », ou des violations avec « message ». */
function errorMessage(error: HttpErrorResponse, fallback: string): string {
  if (0 === error.status) {
    return 'Connexion impossible. Vérifiez votre connexion et réessayez.';
  }
  if (503 === error.status) {
    return 'L’assistant est en pause pour le moment. Réessayez plus tard, ou remplissez le formulaire vous-même.';
  }

  const body = error.error as { message?: string; violations?: Violation[] } | null;

  return body?.message ?? body?.violations?.[0]?.message ?? fallback;
}
