import { DatePipe, DecimalPipe } from '@angular/common';
import { Component, computed, inject, OnInit, signal } from '@angular/core';
import { ActivatedRoute, Params, RouterLink } from '@angular/router';
import { switchMap } from 'rxjs';

import { Accommodation, typeLabel } from '../../core/models/accommodation';
import { AccommodationService } from '../../core/services/accommodation';
import { Availability, BusyPeriod } from '../../core/models/availability';
import { Calendar } from '../../shared/calendar/calendar';
import { BookingForm } from './booking-form/booking-form';

@Component({
  selector: 'cm-accommodation',
  imports: [DatePipe, DecimalPipe, RouterLink, Calendar, BookingForm],
  templateUrl: './accommodation.html',
  styleUrl: './accommodation.scss',
})
export class AccommodationPage implements OnInit {
  private readonly route = inject(ActivatedRoute);
  private readonly accommodations = inject(AccommodationService);

  readonly accommodation = signal<Accommodation | null>(null);
  readonly notFound = signal(false);
  readonly searchParams = signal<Params>({});
  readonly busy = signal<BusyPeriod[]>([]);
  readonly initialArrival = computed(() => this.searchParams()['arrivee'] ?? '');
  readonly initialDeparture = computed(() => this.searchParams()['depart'] ?? '');
  readonly initialGuests = computed(() => Number(this.searchParams()['voyageurs'] ?? 2) || 2);

  readonly typeLabel = typeLabel;

  readonly cameFromSearch = computed(() => Object.keys(this.searchParams()).length > 0);

  ngOnInit(): void {
    this.route.queryParams.subscribe((params) => this.searchParams.set(params));

    this.route.paramMap
      .pipe(switchMap((params) => this.accommodations.getBySlug(params.get('slug') ?? '')))
      .subscribe({
        next: (found) => this.accommodation.set(found),
        error: () => this.notFound.set(true),
      });

    this.route.paramMap
      .pipe(switchMap((params) => this.accommodations.getAvailability(params.get('slug') ?? '')))
      .subscribe({
        next: (availability: Availability) => this.busy.set(availability.busy),
        error: () => this.busy.set([]),
      });
  }
}
