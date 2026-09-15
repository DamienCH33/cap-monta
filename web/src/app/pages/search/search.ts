import { Component, inject, OnInit, signal } from '@angular/core';
import { AccommodationService } from '../../core/services/accommodation';
import { Accommodation } from '../../core/models/accommodation';
import { FormsModule } from '@angular/forms';
import { SearchCriteria } from '../../core/models/search-criteria';

@Component({
  imports: [FormsModule],
  selector: 'cm-search',
  styleUrl: './search.scss',
  templateUrl: './search.html',
})
export class Search implements OnInit {
  private readonly accommodationService = inject(AccommodationService);

  readonly results = signal<Accommodation[]>([]);
  readonly arrival = signal('');
  readonly departure = signal('');
  readonly guests = signal(2);

  ngOnInit(): void {
    this.search();
  }

  search(): void {
    const criteria: SearchCriteria = { guests: this.guests() };

    if (this.arrival() && this.departure()) {
      criteria.arrival = this.arrival();
      criteria.departure = this.departure();
    }

    this.accommodationService.search(criteria).subscribe((accommodations) => {
      this.results.set(accommodations);
    });
  }
}
