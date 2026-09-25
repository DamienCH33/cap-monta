import { HttpErrorResponse } from '@angular/common/http';
import { DatePipe, DecimalPipe } from '@angular/common';
import { Component, computed, effect, inject, OnInit, signal } from '@angular/core';
import { ActivatedRoute, convertToParamMap, Params, RouterLink } from '@angular/router';
import { switchMap } from 'rxjs';

import {
  Accommodation,
  nightlyFromWeek,
  petsPolicyLabel,
  typeLabel,
} from '../../core/models/accommodation';
import { amenityLabel } from '../../core/models/search-filters';
import { Availability, BusyPeriod } from '../../core/models/availability';
import { AccommodationService } from '../../core/services/accommodation';
import { HttpStatus } from '../../core/services/http-status';
import { SeoService } from '../../core/services/seo';
import { Calendar } from '../../shared/calendar/calendar';
import { BookingForm } from './booking-form/booking-form';
import { environment } from '../../../environments/environment';
import { guestsFromQuery, travellerCount } from '../../core/models/guests';
import { Icon } from '../../shared/icon/icon';
import { NavigationOrigin } from '../../core/services/navigation-origin';
import { AuthService } from '../../core/services/auth';
import { OwnerAccommodationService } from '../../core/services/owner-accommodation';
import { PhotoGallery } from './photo-gallery/photo-gallery';
import { ReportListing } from './report-listing/report-listing';

@Component({
  selector: 'cm-accommodation',
  imports: [
    Icon,
    DatePipe,
    DecimalPipe,
    RouterLink,
    Calendar,
    BookingForm,
    PhotoGallery,
    ReportListing,
  ],
  templateUrl: './accommodation.html',
  styleUrl: './accommodation.scss',
})
export class AccommodationPage implements OnInit {
  /** Ouverte depuis « Voir l'annonce » dans Mes logements : le retour y ramène. */
  private readonly openedFromOwnerSpace = inject(NavigationOrigin).isFromOwnerSpace(
    inject(ActivatedRoute).snapshot.paramMap.get('slug'),
  );

  private readonly auth = inject(AuthService);
  private readonly ownerAccommodations = inject(OwnerAccommodationService);

  /**
   * L'annonce appartient à la personne connectée : on lui montre l'aperçu au lieu du formulaire
   * de demande. C'est l'API qui le dit (404 sur le logement d'un autre), pas le chemin suivi
   * pour arriver ici : déconnecté, ou connecté avec un autre compte, on voit la fiche publique.
   */
  readonly ownedByMe = signal(false);

  /** Le lien de retour ramène à Mes logements seulement pour son propriétaire. */
  readonly fromOwnerSpace = computed(() => this.openedFromOwnerSpace && this.ownedByMe());

  private readonly route = inject(ActivatedRoute);
  private readonly accommodations = inject(AccommodationService);
  private readonly seo = inject(SeoService);

  readonly accommodation = signal<Accommodation | null>(null);
  readonly notFound = signal(false);
  readonly unavailable = signal(false);
  private readonly httpStatus = inject(HttpStatus);
  readonly searchParams = signal<Params>({});
  readonly busy = signal<BusyPeriod[]>([]);

  readonly initialArrival = computed(() => this.searchParams()['arrivee'] ?? '');
  readonly initialDeparture = computed(() => this.searchParams()['depart'] ?? '');
  readonly initialGuests = computed(() => {
    const guests = guestsFromQuery(convertToParamMap(this.searchParams()));

    // Arrivée directe sur la fiche, sans recherche : un adulte, pour afficher un devis tout de suite.
    return travellerCount(guests) > 0 ? guests : { ...guests, adults: 1 };
  });

  readonly typeLabel = typeLabel;
  readonly petsPolicyLabel = petsPolicyLabel;
  readonly amenityLabel = amenityLabel;
  readonly nightlyFromWeek = nightlyFromWeek;

  constructor() {
    effect((onCleanup) => {
      const slug = this.accommodation()?.slug;

      if (!slug || !this.auth.isLoggedIn()) {
        this.ownedByMe.set(false);

        return;
      }

      const check = this.ownerAccommodations.get(slug).subscribe({
        next: () => this.ownedByMe.set(true),
        error: () => this.ownedByMe.set(false),
      });
      onCleanup(() => check.unsubscribe());
    });
  }

  readonly cameFromSearch = computed(() => Object.keys(this.searchParams()).length > 0);

  /** « Mobil-home Europa » : le début du texte alternatif de chaque photo. */
  photoLabel(logement: Accommodation): string {
    return [typeLabel(logement.type), logement.district].filter(Boolean).join(' ');
  }

  ngOnInit(): void {
    this.route.queryParams.subscribe((params) => this.searchParams.set(params));

    this.route.paramMap
      .pipe(switchMap((params) => this.accommodations.getBySlug(params.get('slug') ?? '')))
      .subscribe({
        next: (found) => {
          this.accommodation.set(found);
          this.applySeo(found);
        },
        error: (error: HttpErrorResponse) => {
          // 404 : le logement n'existe pas ou n'est plus en ligne. Autre chose : l'API ne
          // répond pas, la page doit le dire (503) au lieu de faire croire qu'il est supprimé.
          if (404 !== error.status) {
            this.unavailable.set(true);
            this.httpStatus.set(503);

            return;
          }

          this.notFound.set(true);
          this.httpStatus.set(404);
          this.seo.apply({
            title: 'Logement introuvable',
            description: 'Ce logement n’est plus en ligne. Voir les autres logements disponibles.',
            path: '/recherche',
            noindex: true,
          });
        },
      });

    this.route.paramMap
      .pipe(switchMap((params) => this.accommodations.getAvailability(params.get('slug') ?? '')))
      .subscribe({
        next: (availability: Availability) => this.busy.set(availability.busy),
        error: () => this.busy.set([]),
      });
  }

  private applySeo(logement: Accommodation): void {
    const type = typeLabel(logement.type);
    const resort = 'chm' === logement.resort ? 'CHM Montalivet' : 'Euronat';
    const place = logement.district ? `${logement.district}, ${resort}` : resort;

    const facts = [
      `${logement.bedrooms} ${logement.bedrooms > 1 ? 'chambres' : 'chambre'}`,
      `${logement.maxCapacity} personnes`,
      logement.surface ? `${logement.surface} m²` : null,
    ]
      .filter((fact) => null !== fact)
      .join(', ');

    const price = logement.priceFrom
      ? ` À partir de ${Math.round(logement.priceFrom / 100)} € la semaine.`
      : '';

    this.seo.apply({
      title: `${type} ${logement.maxCapacity} pers. à ${place}`,
      description: `${type} à louer à ${place} : ${facts}.${price} Disponibilités à jour.`,
      path: `/logement/${logement.slug}`,
      // La couverture sert d'aperçu quand le lien est partagé (Facebook, WhatsApp…).
      image: logement.cover?.url,
    });

    this.seo.setJsonLd({
      '@context': 'https://schema.org',
      '@type': 'VacationRental',
      name: `${type} ${logement.maxCapacity} pers. à ${place}`,
      description: logement.description,
      url: `${environment.siteUrl}/logement/${logement.slug}`,
      image: logement.photos.length ? logement.photos.map((photo) => photo.url) : undefined,
      address: {
        '@type': 'PostalAddress',
        addressLocality: 'chm' === logement.resort ? 'Vendays-Montalivet' : "Grayan-et-l'Hôpital",
        addressRegion: 'Gironde',
        addressCountry: 'FR',
      },
      containedInPlace: {
        '@type': 'Place',
        name: resort,
      },
      numberOfRooms: logement.bedrooms,
      occupancy: {
        '@type': 'QuantitativeValue',
        maxValue: logement.maxCapacity,
        unitCode: 'C62',
      },
      floorSize: logement.surface
        ? { '@type': 'QuantitativeValue', value: logement.surface, unitCode: 'MTK' }
        : undefined,
      amenityFeature: logement.amenities.map((amenity) => ({
        '@type': 'LocationFeatureSpecification',
        name: amenity,
        value: true,
      })),
    });
  }

  /** Barre du bas sur téléphone : descend jusqu'au formulaire sans changer l'adresse. */
  scrollToRequest(event: Event): void {
    event.preventDefault();
    document.getElementById('demande')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
}
