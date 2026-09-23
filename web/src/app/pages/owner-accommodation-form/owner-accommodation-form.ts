import { Location } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { FormBuilder, FormControl, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { catchError, EMPTY, map, Observable, of, switchMap, tap, throwError } from 'rxjs';

import { apiErrorMessage } from '../../core/http/api-error';
import {
  AccommodationChanges,
  DistrictOption,
  NewAccommodation,
  OwnerAccommodation,
  plural,
  statusLabel,
} from '../../core/models/owner-accommodation';
import { PETS_POLICIES, PetsPolicy } from '../../core/models/accommodation';
import { AMENITIES, AMENITY_GROUPS } from '../../core/models/search-filters';
import { AuthService } from '../../core/services/auth';
import { OwnerAccommodationService } from '../../core/services/owner-accommodation';
import { OwnerFlash } from '../../core/services/owner-flash';
import { SeoService } from '../../core/services/seo';
import { PhotoManager } from './photo-manager/photo-manager';

type Field =
  | 'resort'
  | 'type'
  | 'capacity'
  | 'bedrooms'
  | 'district'
  | 'surface'
  | 'amenities'
  | 'petsPolicy'
  | 'description';

/** Ce que veut faire le propriétaire en validant. */
type Intent = 'publish' | 'draft' | 'save';

const FIELDS: Field[] = [
  'resort',
  'type',
  'capacity',
  'bedrooms',
  'district',
  'surface',
  'amenities',
  'petsPolicy',
  'description',
];

/** Même règle que l'API (Accommodation::MIN_DESCRIPTION_LENGTH) ; l'API a le dernier mot. */
const MIN_DESCRIPTION = 50;

/** Messages du navigateur : les mêmes que ceux de l'API, pour ne pas surprendre. */
const MESSAGES: Record<Field, string> = {
  resort: 'Choisissez le domaine.',
  type: 'Choisissez le type de logement.',
  capacity: 'La capacité doit être comprise entre 1 et 12 personnes.',
  bedrooms: 'Le nombre de chambres doit être compris entre 0 et 6.',
  district: 'Quartier inconnu pour ce domaine.',
  surface: 'Indiquez une surface en m² entiers, entre 5 et 200 (par exemple 40).',
  amenities: 'Pas plus de 20 équipements.',
  petsPolicy: 'Choisissez une règle pour les animaux.',
  description: 'La description ne peut pas dépasser 5000 caractères.',
};

const SUCCESS: Record<Intent, string> = {
  publish: 'Votre annonce est en ligne.',
  draft: 'Brouillon enregistré. Vous pourrez le reprendre à tout moment.',
  save: 'Modifications enregistrées.',
};

const LIMITS = {
  capacity: { min: 1, max: 12 },
  bedrooms: { min: 0, max: 6 },
} as const;

/**
 * Un seul formulaire pour ajouter un logement, continuer un brouillon ou modifier une
 * annonce. On peut publier d'une traite, ou enregistrer un brouillon pour finir plus tard.
 */
@Component({
  selector: 'cm-owner-accommodation-form',
  imports: [ReactiveFormsModule, RouterLink, PhotoManager],
  templateUrl: './owner-accommodation-form.html',
  styleUrl: './owner-accommodation-form.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class OwnerAccommodationForm {
  private readonly service = inject(OwnerAccommodationService);
  private readonly router = inject(Router);
  private readonly flash = inject(OwnerFlash);
  private readonly owner = inject(AuthService).currentOwner;

  /** null : création. Sinon, le logement à continuer ou à modifier. */
  readonly slug = inject(ActivatedRoute).snapshot.paramMap.get('slug');

  readonly existing = signal<OwnerAccommodation | null>(null);
  readonly loading = signal(null !== this.slug);
  readonly loadFailed = signal(false);

  readonly resorts = [
    { value: 'chm', label: 'CHM Montalivet' },
    { value: 'euronat', label: 'Euronat' },
  ];

  readonly types = [
    { value: 'mobile_home', label: 'Mobil-home' },
    { value: 'bungalow', label: 'Bungalow' },
    { value: 'caravan', label: 'Caravane' },
    { value: 'chalet', label: 'Chalet' },
    { value: 'studio', label: 'Studio' },
  ];

  /** La même liste que le filtre de la recherche : une annonce doit être trouvée par ce filtre. */
  /** Les équipements par rubrique, dans l'ordre du formulaire. */
  readonly amenityGroups = AMENITY_GROUPS.map((group) => ({
    ...group,
    options: AMENITIES.filter((amenity) => amenity.group === group.key),
  }));
  readonly petsPolicies = PETS_POLICIES;
  readonly limits = LIMITS;
  readonly minDescription = MIN_DESCRIPTION;
  readonly plural = plural;
  readonly statusLabel = statusLabel;

  readonly form = inject(FormBuilder).nonNullable.group({
    resort: ['', Validators.required],
    type: ['', Validators.required],
    capacity: [4, [Validators.required, Validators.min(1), Validators.max(12)]],
    bedrooms: [1, [Validators.required, Validators.min(0), Validators.max(6)]],
    district: [''],
    surface: new FormControl<number | null>(null, [
      Validators.min(5),
      Validators.max(200),
      Validators.pattern(/^\d+$/),
    ]),
    amenities: new FormControl<string[]>([], {
      nonNullable: true,
      validators: Validators.maxLength(20),
    }),
    petsPolicy: new FormControl<PetsPolicy>('on_request', { nonNullable: true }),
    description: ['', Validators.maxLength(5000)],
  });

  private readonly districts = signal<DistrictOption[]>([]);
  private readonly resort = toSignal(this.form.controls.resort.valueChanges, { initialValue: '' });
  private readonly petsPolicy = toSignal(this.form.controls.petsPolicy.valueChanges, {
    initialValue: this.form.controls.petsPolicy.value,
  });

  /** Ce que la règle choisie change pour le voyageur, sous les trois choix. */
  readonly petsHint = computed(
    () => PETS_POLICIES.find((policy) => policy.value === this.petsPolicy())?.hint ?? '',
  );

  /** Le quartier n'a de sens qu'au CHM : Euronat n'a pas encore de quartiers référencés. */
  readonly showDistrict = computed(() => 'chm' === this.resort());
  readonly resortDistricts = computed(() =>
    this.districts().filter((district) => district.resort === this.resort()),
  );

  readonly descriptionLength = toSignal(
    this.form.controls.description.valueChanges.pipe(map((text) => text.trim().length)),
    { initialValue: 0 },
  );

  readonly status = computed(() => this.existing()?.status ?? null);

  /** Sans email confirmé, l'API refuse la publication : on ne propose que le brouillon. */
  readonly canPublish = computed(() => false !== this.owner()?.verified);

  readonly title = computed(() => {
    if (null === this.slug) {
      return 'Ajouter un logement';
    }

    return 'draft' === this.status() ? "Continuer l'annonce" : "Modifier l'annonce";
  });

  /** Le bouton principal, selon l'état de l'annonce. */
  readonly primary = computed<{ intent: Intent; label: string } | null>(() => {
    switch (this.status()) {
      case 'published':
        return { intent: 'save', label: 'Enregistrer les modifications' };
      case 'archived':
        return this.canPublish()
          ? { intent: 'publish', label: 'Enregistrer et remettre en ligne' }
          : null;
      default:
        return this.canPublish() ? { intent: 'publish', label: "Publier l'annonce" } : null;
    }
  });

  /** Le bouton secondaire : enregistrer sans publier. */
  readonly secondary = computed<{ intent: Intent; label: string } | null>(() => {
    switch (this.status()) {
      case 'published':
        return null;
      case 'archived':
        return { intent: 'save', label: 'Enregistrer' };
      default:
        return { intent: 'draft', label: 'Enregistrer le brouillon' };
    }
  });

  readonly saving = signal<Intent | null>(null);

  /** Passe à vrai à la première tentative : avant, on n'accuse personne. */
  readonly submitted = signal(false);

  /** Vrai quand l'action demandée exige une annonce complète (publier, ou modifier une annonce en ligne). */
  private readonly checkPublication = signal(false);

  readonly serverErrors = signal<Partial<Record<Field, string>>>({});
  readonly generalError = signal<string | null>(null);

  /** Information neutre (pas une erreur) : brouillon enregistré automatiquement, par exemple. */
  readonly notice = signal<string | null>(null);

  /** Tenu à jour par la section Photos : il en faut au moins une pour publier. */
  readonly photoCount = signal(0);

  /** Message sous la section Photos quand la publication est demandée sans photo. */
  readonly photosError = computed(() =>
    this.submitted() && this.checkPublication() && 0 === this.photoCount()
      ? 'Ajoutez au moins une photo pour publier.'
      : null,
  );

  private readonly location = inject(Location);

  /**
   * Donnée à la section Photos : une photo a besoin d'un logement enregistré. Pour une
   * nouvelle annonce, on l'enregistre donc en brouillon à l'ajout de la première photo,
   * avec ce qui est déjà saisi. On reste sur la page : rien n'est perdu.
   */
  readonly ensureSaved = (): Observable<string> => {
    const existing = this.existing();

    if (null !== existing) {
      return of(existing.slug);
    }

    this.submitted.set(true);
    this.checkPublication.set(false);
    this.form.markAllAsTouched();

    if (FIELDS.some((field) => null !== this.error(field))) {
      return throwError(
        () =>
          new Error(
            "Choisissez d'abord le domaine et le type de logement : l'annonce est enregistrée en brouillon pour recevoir vos photos.",
          ),
      );
    }

    return this.service.create(this.newAccommodation()).pipe(
      tap((created) => {
        this.existing.set(created);
        this.form.controls.resort.disable();
        // L'adresse devient celle du brouillon, sans recharger la page ni perdre la saisie.
        this.location.replaceState(`/mon-espace/logements/${created.slug}/modifier`);
        this.notice.set('Brouillon enregistré automatiquement pour recevoir vos photos.');
      }),
      map((created) => created.slug),
    );
  };

  constructor() {
    inject(SeoService).apply({
      title: null === this.slug ? 'Ajouter un logement' : "Modifier l'annonce",
      description: 'Décrivez votre logement et publiez votre annonce.',
      path: '/mon-espace/logements',
      noindex: true,
    });

    this.service.districts().subscribe({
      next: (list) => this.districts.set(list),
      // Sans la liste, on enregistre quand même : le quartier se précisera plus tard.
      error: () => this.districts.set([]),
    });

    if (null !== this.slug) {
      this.load(this.slug);
    }
  }

  /** Changer de domaine efface le quartier : il n'appartient plus au domaine choisi. */
  onResortChange(): void {
    this.form.controls.district.setValue('');
    this.forget('resort');
  }

  step(field: 'capacity' | 'bedrooms', delta: number): void {
    const control = this.form.controls[field];
    const { min, max } = LIMITS[field];

    control.setValue(Math.min(max, Math.max(min, control.value + delta)));
    this.forget(field);
  }

  hasAmenity(key: string): boolean {
    return this.form.controls.amenities.value.includes(key);
  }

  toggleAmenity(key: string): void {
    const control = this.form.controls.amenities;
    const current = control.value;

    control.setValue(current.includes(key) ? current.filter((k) => k !== key) : [...current, key]);
    this.forget('amenities');
  }

  /** Le message à afficher sous un champ, ou null. L'API a le dernier mot. */
  error(field: Field): string | null {
    const server = this.serverErrors()[field];

    if (server) {
      return server;
    }
    if (!this.submitted()) {
      return null;
    }
    if (this.form.controls[field].invalid) {
      return MESSAGES[field];
    }

    return this.checkPublication() ? this.publicationError(field) : null;
  }

  /** Un champ corrigé ne garde pas l'ancien refus de l'API. */
  forget(field: Field): void {
    this.serverErrors.update((errors) => {
      const next = { ...errors };
      delete next[field];
      return next;
    });
  }

  submit(intent: Intent): void {
    if (null !== this.saving()) {
      return;
    }

    this.submitted.set(true);
    this.checkPublication.set(
      'publish' === intent || ('save' === intent && 'published' === this.status()),
    );
    this.serverErrors.set({});
    this.generalError.set(null);
    this.form.markAllAsTouched();

    if (FIELDS.some((field) => null !== this.error(field)) || null !== this.photosError()) {
      this.generalError.set(
        'Certaines informations sont à compléter : voir les messages en rouge.',
      );
      return;
    }

    this.saving.set(intent);

    const existing = this.existing();
    const save$: Observable<OwnerAccommodation> =
      null === existing
        ? this.service.create(this.newAccommodation())
        : this.service.update(existing.slug, this.changes(existing));

    save$
      .pipe(
        switchMap((saved) =>
          'publish' === intent
            ? this.service.publish(saved.slug).pipe(
                catchError((error: HttpErrorResponse) => {
                  this.publishRefused(saved, error, null === existing);
                  return EMPTY;
                }),
              )
            : of(saved),
        ),
      )
      .subscribe({
        next: (saved) => this.done(saved, intent),
        error: (error: HttpErrorResponse) => this.refused(error),
      });
  }

  private load(slug: string): void {
    this.service.get(slug).subscribe({
      next: (item) => {
        this.existing.set(item);
        this.photoCount.set(item.photos.length);
        this.form.patchValue({
          resort: item.resort,
          type: item.type,
          capacity: item.capacity,
          bedrooms: item.bedrooms,
          district: item.districtSlug ?? '',
          surface: item.surface,
          amenities: item.amenities,
          petsPolicy: item.petsPolicy,
          description: item.description,
        });
        // Un mobil-home ne change pas de domaine : pour cela, on crée un autre logement.
        this.form.controls.resort.disable();
        this.loading.set(false);

        const flash = this.flash.take();
        if (flash?.slug === item.slug) {
          this.generalError.set(flash.message);
        }
      },
      error: () => {
        this.loading.set(false);
        this.loadFailed.set(true);
      },
    });
  }

  private publicationError(field: Field): string | null {
    const value = this.form.getRawValue();

    if ('description' === field && value.description.trim().length < MIN_DESCRIPTION) {
      return `Ajoutez une description d'au moins ${MIN_DESCRIPTION} caractères pour publier.`;
    }
    if ('district' === field && 'chm' === value.resort && '' === value.district) {
      return 'Choisissez le quartier pour publier.';
    }

    return null;
  }

  private newAccommodation(): NewAccommodation {
    const value = this.form.getRawValue();

    return {
      resort: value.resort,
      type: value.type,
      capacity: value.capacity,
      bedrooms: value.bedrooms,
      surface: value.surface,
      amenities: value.amenities,
      petsPolicy: value.petsPolicy,
      description: value.description.trim(),
      district: 'chm' === value.resort && '' !== value.district ? value.district : null,
    };
  }

  /** N'envoyer que ce qui a changé : un champ absent n'est pas touché par l'API. */
  private changes(existing: OwnerAccommodation): AccommodationChanges {
    const value = this.form.getRawValue();
    const changes: AccommodationChanges = {};
    const district = 'chm' === value.resort && '' !== value.district ? value.district : null;
    const description = value.description.trim();

    if (value.type !== existing.type) {
      changes.type = value.type;
    }
    if (value.capacity !== existing.capacity) {
      changes.capacity = value.capacity;
    }
    if (value.bedrooms !== existing.bedrooms) {
      changes.bedrooms = value.bedrooms;
    }
    if (value.surface !== existing.surface) {
      changes.surface = value.surface;
    }
    if (value.amenities.join('|') !== existing.amenities.join('|')) {
      changes.amenities = value.amenities;
    }
    if (value.petsPolicy !== existing.petsPolicy) {
      changes.petsPolicy = value.petsPolicy;
    }
    if (description !== existing.description) {
      changes.description = description;
    }
    if (district !== existing.districtSlug) {
      changes.district = district;
    }

    return changes;
  }

  private done(saved: OwnerAccommodation, intent: Intent): void {
    this.flash.set({ slug: saved.slug, message: SUCCESS[intent], tone: 'ok' });
    void this.router.navigate(['/mon-espace/logements']);
  }

  /** Enregistré, mais la publication a été refusée (email non confirmé, par exemple). */
  private publishRefused(
    saved: OwnerAccommodation,
    error: HttpErrorResponse,
    created: boolean,
  ): void {
    const message = `Votre annonce est enregistrée en brouillon, mais n'a pas pu être publiée : ${apiErrorMessage(error, 'réessayez dans un instant.')}`;

    if (created) {
      // Le brouillon existe maintenant : on continue sur sa page, sans rien perdre.
      this.flash.set({ slug: saved.slug, message, tone: 'error' });
      void this.router.navigate(['/mon-espace/logements', saved.slug, 'modifier']);
      return;
    }

    this.saving.set(null);
    this.existing.set(saved);
    this.generalError.set(message);
  }

  private refused(error: HttpErrorResponse): void {
    this.saving.set(null);

    const body = error.error as { violations?: { propertyPath: string; message: string }[] } | null;

    if (422 === error.status && body?.violations?.length) {
      this.serverErrors.set(
        Object.fromEntries(body.violations.map((v) => [v.propertyPath, v.message])) as Partial<
          Record<Field, string>
        >,
      );
      this.generalError.set('Certaines informations sont à corriger : voir les messages en rouge.');
    } else {
      this.generalError.set(apiErrorMessage(error, "L'enregistrement n'a pas abouti. Réessayez."));
    }
  }
}
