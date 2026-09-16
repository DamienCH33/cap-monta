import { Component, inject, input, linkedSignal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';

@Component({
  selector: 'cm-search-bar',
  imports: [FormsModule],
  templateUrl: './search-bar.html',
  styleUrl: './search-bar.scss',
})
export class SearchBar {
  private readonly router = inject(Router);

  readonly initialArrival = input('');
  readonly initialDeparture = input('');
  readonly initialGuests = input(2);

  readonly arrival = linkedSignal(() => this.initialArrival());
  readonly departure = linkedSignal(() => this.initialDeparture());
  readonly guests = linkedSignal(() => this.initialGuests());

  submit(): void {
    this.router.navigate(['/recherche'], {
      queryParams: {
        arrivee: this.arrival() || null,
        depart: this.departure() || null,
        voyageurs: this.guests() || null,
      },
    });
  }
}
