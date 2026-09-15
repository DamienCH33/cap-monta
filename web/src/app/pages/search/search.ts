import { Component, inject, OnInit, signal } from '@angular/core';
import { AccommodationService } from '../../core/services/accommodation';
import { Accommodation } from '../../core/models/accommodation';

@Component({
  imports: [],
  selector: 'cm-search',
  styleUrl: './search.scss',
  templateUrl: './search.html',
})
export class Search implements OnInit {
  private readonly accommodationService = inject(AccommodationService);

  readonly results = signal<Accommodation[]>([]);

  ngOnInit(): void {
    this.accommodationService.search({}).subscribe((accommodations) => {
      this.results.set(accommodations);
    });
  }
}
