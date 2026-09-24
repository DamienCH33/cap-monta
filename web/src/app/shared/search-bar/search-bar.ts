import { Component, inject, input, linkedSignal } from '@angular/core';
import { Router } from '@angular/router';

import { Guests, guestsToQuery, NO_GUESTS } from '../../core/models/guests';
import { departureAfter } from '../../core/models/stay-dates';
import { DateRange } from '../date-range/date-range';
import { GuestPicker } from '../guest-picker/guest-picker';

@Component({
  selector: 'cm-search-bar',
  imports: [DateRange, GuestPicker],
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

  /** Le calendrier choisit l'arrivée puis le départ ; il vide le départ entre les deux. */
  onArrivalChange(arrival: string): void {
    this.arrival.set(arrival);
  }

  submit(): void {
    // Une arrivée sans départ : une semaine, la durée la plus courante au CHM.
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
