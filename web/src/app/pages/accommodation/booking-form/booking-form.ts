import { DecimalPipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, input, linkedSignal, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';

import { BookingRequest } from '../../../core/models/booking-request';
import { Quote } from '../../../core/models/quote';
import { BookingService } from '../../../core/services/booking';

@Component({
  selector: 'cm-booking-form',
  imports: [FormsModule, DecimalPipe],
  templateUrl: './booking-form.html',
  styleUrl: './booking-form.scss',
})
export class BookingForm implements OnInit {
  private readonly booking = inject(BookingService);

  readonly slug = input.required<string>();
  readonly initialArrival = input('');
  readonly initialDeparture = input('');
  readonly initialGuests = input(2);

  readonly arrival = linkedSignal(() => this.initialArrival());
  readonly departure = linkedSignal(() => this.initialDeparture());
  readonly adults = linkedSignal(() => this.initialGuests());
  readonly children = signal(0);

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

  refreshQuote(): void {
    const arrival = this.arrival();
    const departure = this.departure();

    this.error.set(null);

    if ('' === arrival || '' === departure) {
      this.quote.set(null);

      return;
    }

    this.booking.quote(this.slug(), arrival, departure, this.adults() + this.children()).subscribe({
      next: (quote) => this.quote.set(quote),
      error: () => this.quote.set(null),
    });
  }

  submit(): void {
    const arrival = this.arrival();
    const departure = this.departure();

    if ('' === arrival || '' === departure) {
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
        adults: this.adults(),
        children: this.children(),
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
        return `Ce logement accueille ${quote.maxCapacity} personnes au maximum.`;
      case 'stay_too_short':
        return `Le propriétaire demande ${quote.minimumNights} nuits minimum sur cette période.`;
      default:
        return 'Ces dates ne peuvent pas être réservées.';
    }
  }

  private readError(response: HttpErrorResponse): string {
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
}

interface Violation {
  propertyPath: string;
  message: string;
}
