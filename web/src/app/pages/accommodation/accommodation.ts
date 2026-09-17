import { DatePipe, DecimalPipe } from '@angular/common';
import { Component, computed, inject, OnInit, signal } from '@angular/core';
import { ActivatedRoute, convertToParamMap, Params, RouterLink } from '@angular/router';
import { switchMap } from 'rxjs';

import { Accommodation, typeLabel } from '../../core/models/accommodation';
import { Availability, BusyPeriod } from '../../core/models/availability';
import { AccommodationService } from '../../core/services/accommodation';
import { SeoService } from '../../core/services/seo';
import { Calendar } from '../../shared/calendar/calendar';
import { BookingForm } from './booking-form/booking-form';
import { environment } from '../../../environments/environment';
import { guestsFromQuery, travellerCount } from '../../core/models/guests';

@Component({
  selector: 'cm-accommodation',
  imports: [DatePipe, DecimalPipe, RouterLink, Calendar, BookingForm],
  templateUrl: './accommodation.html',
  styleUrl: './accommodation.scss',
})
export class AccommodationPage implements OnInit {
  private readonly route = inject(ActivatedRoute);
  private readonly accommodations = inject(AccommodationService);
  private readonly seo = inject(SeoService);

  readonly accommodation = signal<Accommodation | null>(null);
  readonly notFound = signal(false);
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

  readonly cameFromSearch = computed(() => Object.keys(this.searchParams()).length > 0);

  ngOnInit(): void {
    this.route.queryParams.subscribe((params) => this.searchParams.set(params));

    this.route.paramMap
      .pipe(switchMap((params) => this.accommodations.getBySlug(params.get('slug') ?? '')))
      .subscribe({
        next: (found) => {
          this.accommodation.set(found);
          this.applySeo(found);
        },
        error: () => {
          this.notFound.set(true);
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
    });

    this.seo.setJsonLd({
      '@context': 'https://schema.org',
      '@type': 'VacationRental',
      name: `${type} ${logement.maxCapacity} pers. à ${place}`,
      description: logement.description,
      url: `${environment.siteUrl}/logement/${logement.slug}`,
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
}
