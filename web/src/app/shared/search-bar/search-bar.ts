import { Component, inject, input, linkedSignal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';

import { Guests, guestsToQuery, NO_GUESTS } from '../../core/models/guests';
import { departureAfter, plusDays, today } from '../../core/models/stay-dates';
import { GuestPicker } from '../guest-picker/guest-picker';

@Component({
  selector: 'cm-search-bar',
  imports: [FormsModule, GuestPicker],
  templateUrl: './search-bar.html',
  styleUrl: './search-bar.scss',
})
export class SearchBar {
  private readonly router = inject(Router);

  readonly initialArrival = input('');
  readonly initialDeparture = input('');
  readonly initialGuests = input<Guests>(NO_GUESTS);

  readonly arrival = linkedSignal(() => this.initialArrival());
  readonly departure = linkedSignal(() => this.initialDeparture());
  readonly guests = linkedSignal(() => this.initialGuests());

  readonly today = today();
  readonly plusDays = plusDays;

  /** Une arrivée après le départ choisi déplace le départ une semaine plus loin. */
  onArrivalChange(arrival: string): void {
    this.arrival.set(arrival);
    this.departure.set(departureAfter(arrival, this.departure()));
  }

  submit(): void {
    // Le navigateur laisse taper une date hors des bornes min : on corrige plutôt que d'envoyer.
    if (this.arrival()) {
      this.departure.set(departureAfter(this.arrival(), this.departure()));
    }

    this.router.navigate(['/recherche'], {
      queryParams: {
        arrivee: this.arrival() || null,
        depart: this.departure() || null,
        ...guestsToQuery(this.guests()),
      },
      queryParamsHandling: 'merge',
    });
  }
}
