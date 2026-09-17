import { Component, inject, input, linkedSignal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';

import { Guests, guestsToQuery, NO_GUESTS } from '../../core/models/guests';
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

  submit(): void {
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
