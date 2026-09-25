import { HttpErrorResponse } from '@angular/common/http';
import { Component, computed, inject, input, linkedSignal, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';

import { currentLang } from '../../../core/i18n/lang';
import { BookingRequest } from '../../../core/models/booking-request';
import { PetsPolicy } from '../../../core/models/accommodation';
import { Guests, NO_GUESTS, travellerCount } from '../../../core/models/guests';
import { Quote } from '../../../core/models/quote';
import { ExtraCode, EXTRA_LABELS, euros } from '../../../core/models/stay-terms';
import { BookingService } from '../../../core/services/booking';
import { GuestRequests } from '../../../core/services/guest-requests';
import { BusyPeriod } from '../../../core/models/availability';
import { DateRange } from '../../../shared/date-range/date-range';
import { GuestPicker } from '../../../shared/guest-picker/guest-picker';
import { plusDays, today } from '../../../core/models/stay-dates';

@Component({
  selector: 'cm-booking-form',
  imports: [FormsModule, DateRange, GuestPicker, RouterLink],
  templateUrl: './booking-form.html',
  styleUrl: './booking-form.scss',
})
export class BookingForm implements OnInit {
  private readonly booking = inject(BookingService);
  private readonly guestRequests = inject(GuestRequests);

  /** « Bungalow · Hawaï », pour retrouver la demande dans « Mes demandes ». */
  readonly title = input('');

  readonly slug = input.required<string>();
  readonly initialArrival = input('');
  readonly initialDeparture = input('');
  readonly initialGuests = input<Guests>(NO_GUESTS);
  readonly petsPolicy = input<PetsPolicy>('on_request');
  /** Périodes prises, barrées dans le calendrier de dates. */
  readonly busy = input<BusyPeriod[]>([]);

  readonly arrival = linkedSignal(() => this.initialArrival());
  readonly departure = linkedSignal(() => this.initialDeparture());
  readonly guests = linkedSignal(() => this.initialGuests());

  readonly today = today();
  /** Première arrivée possible : demain. Le propriétaire doit avoir le temps de répondre. */
  readonly firstArrival = plusDays(today(), 1);

  /** Les erreurs de l'API sur les dates s'affichent sous le calendrier. */
  readonly dateFields = ['arrival', 'departure'] as const;

  /** Le calendrier vide le départ quand on choisit une nouvelle arrivée : pas de devis entre les deux. */
  onArrivalChange(arrival: string): void {
    this.arrival.set(arrival);
    this.dateFields.forEach((field) => this.clearViolation(field));
    this.refreshQuote();
  }

  onDepartureChange(departure: string): void {
    this.departure.set(departure);
    this.dateFields.forEach((field) => this.clearViolation(field));
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

  readonly euros = euros;
  /** Les messages de l'API sont en français : ailleurs, on les remplace par des messages traduits. */
  readonly french = 'fr' === currentLang();
  readonly mandatoryExtras = computed(() =>
    (this.quote()?.extras ?? []).filter((e) => !e.optional),
  );
  readonly optionalExtras = computed(() => (this.quote()?.extras ?? []).filter((e) => e.optional));

  extraLabel(code: ExtraCode): string {
    return EXTRA_LABELS[code];
  }

  /** Dans une phrase ; les noms allemands gardent leur majuscule. */
  lower(code: ExtraCode): string {
    return 'de' === currentLang() ? EXTRA_LABELS[code] : EXTRA_LABELS[code].toLowerCase();
  }

  /** « Taxe de séjour et redevance du domaine » */
  unknownFeesLabel(codes: ExtraCode[]): string {
    const labels = codes.map((code, i) => (0 === i ? EXTRA_LABELS[code] : this.lower(code)));

    return labels.join($localize`:@@common.and: et `);
  }

  /** Le bouton dit ce qui manque plutôt que de rester grisé sans explication. */
  readonly submitLabel = computed(() => {
    if (this.sending()) {
      return $localize`:@@common.sending:Envoi…`;
    }
    if ('' === this.arrival() || '' === this.departure()) {
      return $localize`:@@booking.btn-dates:Choisissez vos dates`;
    }
    if (0 === travellerCount(this.guests())) {
      return $localize`:@@booking.btn-guests:Indiquez les voyageurs`;
    }
    const quote = this.quote();
    if (null !== quote && !quote.available) {
      return $localize`:@@booking.btn-impossible:Dates impossibles`;
    }

    return $localize`:@@booking.btn-send:Envoyer la demande`;
  });

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

    this.booking
      .quote(this.slug(), arrival, departure, travellers, this.guests().pets, this.guests().adults)
      .subscribe({
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
          this.guestRequests.remember({
            token: created.trackingToken,
            title: this.title() || $localize`:@@type.other:Logement`,
            start: arrival,
            end: departure,
          });
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
        return $localize`:@@refusal.unavailable:Ces dates sont déjà prises.`;
      case 'too_many_guests':
        return $localize`:@@refusal.too-many:Ce logement accueille ${quote.maxCapacity}:count: personnes au maximum, bébés non compris.`;
      case 'stay_too_short':
        return $localize`:@@refusal.too-short:Le propriétaire demande ${quote.minimumNights}:count: nuits minimum sur cette période.`;
      case 'pets_not_allowed':
        return $localize`:@@refusal.pets:Le propriétaire n'accepte pas les animaux dans ce logement.`;
      default:
        return $localize`:@@refusal.other:Ces dates ne peuvent pas être réservées.`;
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
      return $localize`:@@booking.error-429:Plusieurs demandes viennent d'être envoyées depuis votre connexion. Réessayez dans un quart d'heure.`;
    }

    // L'API explique le refus en français : dates prises, demande déjà envoyée… Dans les
    // autres langues, un message traduit plus général.
    if (409 === response.status) {
      const detail = (response.error as { detail?: string } | null)?.detail;

      return detail && this.french
        ? detail
        : $localize`:@@booking.error-409:Ces dates viennent d'être prises. Choisissez-en d'autres.`;
    }

    if (422 === response.status) {
      const violations: Record<string, string> = {};

      for (const violation of (response.error?.violations ?? []) as Violation[]) {
        violations[violation.propertyPath] = this.french
          ? violation.message
          : $localize`:@@booking.check-field:Vérifiez ce champ.`;
      }

      this.violations.set(violations);

      return $localize`:@@booking.error-422:Certaines informations sont incorrectes.`;
    }

    return $localize`:@@common.send-failed:L'envoi a échoué. Réessayez dans un instant.`;
  }
}

interface Violation {
  propertyPath: string;
  message: string;
}
