import { DatePipe, DecimalPipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';

import { apiErrorMessage } from '../../core/http/api-error';
import {
  bookingPrice,
  bookingStatusLabel,
  TrackedBookingRequest,
  travellers,
} from '../../core/models/booking-answer';
import { BookingService } from '../../core/services/booking';
import { GuestRequests } from '../../core/services/guest-requests';
import { SeoService } from '../../core/services/seo';

/**
 * La page du voyageur, sans compte : le lien privé reçu par email suffit. Il y suit sa
 * demande, retrouve les coordonnées du propriétaire une fois acceptée, et peut annuler.
 */
@Component({
  selector: 'cm-booking-tracking',
  imports: [DatePipe, DecimalPipe, RouterLink],
  templateUrl: './booking-tracking.html',
  styleUrl: './booking-tracking.scss',
})
export class BookingTracking {
  private readonly booking = inject(BookingService);
  private readonly token = inject(ActivatedRoute).snapshot.paramMap.get('token') ?? '';

  readonly request = signal<TrackedBookingRequest | null>(null);
  readonly notFound = signal(false);
  readonly confirming = signal(false);
  readonly saving = signal(false);
  readonly error = signal<string | null>(null);

  readonly statusLabel = bookingStatusLabel;
  readonly travellers = travellers;
  readonly bookingPrice = bookingPrice;

  constructor() {
    const guestRequests = inject(GuestRequests);

    inject(SeoService).apply({
      title: 'Votre demande de réservation',
      description: 'Suivez votre demande de réservation.',
      path: '/recherche',
      noindex: true,
    });

    this.booking.track(this.token).subscribe({
      next: (request) => {
        this.request.set(request);
        // Ouvert depuis l'email sur un autre appareil : on le retient ici aussi.
        guestRequests.remember({
          token: this.token,
          title: request.accommodation.title,
          start: request.start,
          end: request.end,
        });
      },
      error: () => this.notFound.set(true),
    });
  }

  cancel(): void {
    this.saving.set(true);
    this.error.set(null);

    this.booking.cancelTracked(this.token).subscribe({
      next: (request) => {
        this.request.set(request);
        this.saving.set(false);
        this.confirming.set(false);
      },
      error: (error: HttpErrorResponse) => {
        this.saving.set(false);
        this.error.set(apiErrorMessage(error, 'L’annulation a échoué. Réessayez.'));
      },
    });
  }
}
