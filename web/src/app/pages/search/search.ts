import { Component, inject, OnInit, signal } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { map, switchMap, tap } from 'rxjs';

import { Accommodation } from '../../core/models/accommodation';
import { SearchCriteria } from '../../core/models/search-criteria';
import { AccommodationService } from '../../core/services/accommodation';
import { AccommodationCard } from '../../shared/accommodation-card/accommodation-card';
import { SearchBar } from '../../shared/search-bar/search-bar';

@Component({
  selector: 'cm-search',
  imports: [SearchBar, AccommodationCard],
  templateUrl: './search.html',
  styleUrl: './search.scss',
})
export class Search implements OnInit {
  private readonly route = inject(ActivatedRoute);
  private readonly accommodations = inject(AccommodationService);

  readonly criteria = signal<SearchCriteria>({});
  readonly results = signal<Accommodation[]>([]);

  ngOnInit(): void {
    this.route.queryParamMap
      .pipe(
        map((params) => ({
          arrival: params.get('arrivee') ?? undefined,
          departure: params.get('depart') ?? undefined,
          guests: Number(params.get('voyageurs')) || undefined,
        })),
        tap((criteria) => this.criteria.set(criteria)),
        switchMap((criteria) => this.accommodations.search(criteria)),
      )
      .subscribe((found) => this.results.set(found));
  }
}
