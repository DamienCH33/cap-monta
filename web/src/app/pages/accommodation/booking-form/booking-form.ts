import { DecimalPipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, input, linkedSignal, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';

import { BookingRequest } from '../../../core/models/booking-request';
import { Guests, NO_GUESTS, travellerCount } from '../../../core/models/guests';
import { Quote } from '../../../core/models/quote';
import { BookingService } from '../../../core/services/booking';
import { GuestPicker } from '../../../shared/guest-picker/guest-picker';
import { departureAfter, plusDays, today } from '../../../core/models/stay-dates';

@Component({
  selector: 'cm-booking-form',
  imports: [FormsModule, DecimalPipe, GuestPicker, RouterLink],
  templateUrl: './booking-form.html',
  styleUrl: './booking-form.scss',
})
export class BookingForm implements OnInit {
  private readonly booking = inject(BookingService);

  readonly slug = input.required<string>();
  readonly initialArrival = input('');
  readonly initialDeparture = input('');
  readonly initialGuests = input<Guests>(NO_GUESTS);

  readonly arrival = linkedSignal(() => this.initialArrival());
  readonly departure = linkedSignal(() => this.initialDeparture());
  readonly guests = linkedSignal(() => this.initialGuests());

  readonly today = today();
  readonly plusDays = plusDays;

  onArrivalChange(arrival: string): void {
    this.arrival.set(arrival);
    this.departure.set(departureAfter(arrival, this.departure()));
    this.clearViolation('arrival');
    this.clearViolation('departure');
    this.refreshQuote();
  }

  /** Les champs de l'API qui concernent les voyageurs, pour afficher leurs erreurs sous le sélecteur. */
  readonly guestFields = ['adults', 'children', 'infants', 'pets'] as const;

  readonly guestName = signal('');
  readonly guestEmail = signal('');
  readonly guestPhone = signal('');
  readonly message = signal('');

  readonly quote = signal<Quote | null>(null);
  readonly sending = signal(false);
  readonly sent = signal<BookingRequest | null>(null);
  readonly error = signal<string | null>(null);

  readonly violations = signal<Record<string, string>>({});

  ngOnInit(): void {
    this.refreshQuote();
  }

  onGuestsChange(guests: Guests): void {
    this.guests.set(guests);
    this.guestFields.forEach((field) => this.clearViolation(field));
    this.refreshQuote();
  }

  refreshQuote(): void {
    const arrival = this.arrival();
    const departure = this.departure();
    const travellers = travellerCount(this.guests());

    this.error.set(null);

    if ('' === arrival || '' === departure || 0 === travellers) {
      this.quote.set(null);

      return;
    }

    this.booking.quote(this.slug(), arrival, departure, travellers).subscribe({
      next: (quote) => this.quote.set(quote),
      error: () => this.quote.set(null),
    });
  }

  submit(): void {
    const arrival = this.arrival();
    const departure = this.departure();
    const guests = this.guests();

    if ('' === arrival || '' === departure || 0 === travellerCount(guests)) {
      return;
    }

    this.sending.set(true);
    this.error.set(null);
    this.violations.set({});

    this.booking
      .send({
        accommodationSlug: this.slug(),
        arrival,
        departure,
        adults: guests.adults,
        children: guests.children,
        infants: guests.infants,
        pets: guests.pets,
        guestName: this.guestName(),
        guestEmail: this.guestEmail(),
        guestPhone: this.guestPhone() || null,
        message: this.message() || null,
      })
      .subscribe({
        next: (created) => {
          this.sent.set(created);
          this.sending.set(false);
        },
        error: (response: HttpErrorResponse) => {
          this.error.set(this.readError(response));
          this.sending.set(false);
        },
      });
  }

  refusalLabel(quote: Quote): string {
    switch (quote.refusal) {
      case 'unavailable':
        return 'Ces dates sont déjà prises.';
      case 'too_many_guests':
        return `Ce logement accueille ${quote.maxCapacity} personnes au maximum, bébés non compris.`;
      case 'stay_too_short':
        return `Le propriétaire demande ${quote.minimumNights} nuits minimum sur cette période.`;
      default:
        return 'Ces dates ne peuvent pas être réservées.';
    }
  }

  clearViolation(field: string): void {
    this.violations.update((current) => {
      if (!(field in current)) {
        return current;
      }

      const next = { ...current };
      delete next[field];

      return next;
    });
  }

  private readError(response: HttpErrorResponse): string {
    // Plafond anti-abus de l'API : 5 demandes par quart d'heure depuis une même connexion.
    if (429 === response.status) {
      return "Plusieurs demandes viennent d'être envoyées depuis votre connexion. Réessayez dans un quart d'heure.";
    }

    if (409 === response.status) {
      return "Ces dates viennent d'être prises. Choisissez-en d'autres.";
    }

    if (422 === response.status) {
      const violations: Record<string, string> = {};

      for (const violation of (response.error?.violations ?? []) as Violation[]) {
        violations[violation.propertyPath] = violation.message;
      }

      this.violations.set(violations);

      return 'Certaines informations sont incorrectes.';
    }

    return "L'envoi a échoué. Réessayez dans un instant.";
  }
}

interface Violation {
  propertyPath: string;
  message: string;
}
