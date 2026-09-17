import { DatePipe } from '@angular/common';
import { Component, computed, inject, OnInit, signal } from '@angular/core';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { catchError, map, of, switchMap, tap } from 'rxjs';

import { Accommodation } from '../../core/models/accommodation';
import { Guests, guestsFromQuery, NO_GUESTS, travellerCount } from '../../core/models/guests';
import { SearchCriteria } from '../../core/models/search-criteria';
import {
  activeFilterCount,
  filterChips,
  filtersFromQuery,
  filtersToQuery,
  NO_FILTERS,
  SearchFilters,
  SORT_OPTIONS,
} from '../../core/models/search-filters';
import { StaySuggestion } from '../../core/models/stay-suggestion';
import { AccommodationService } from '../../core/services/accommodation';
import { SeoService } from '../../core/services/seo';
import { AccommodationCard } from '../../shared/accommodation-card/accommodation-card';

import { SearchBar } from '../../shared/search-bar/search-bar';
import { FilterSheet } from '../../shared/filter-sheet/filter-sheet';

@Component({
  selector: 'cm-search',
  imports: [SearchBar, AccommodationCard, FilterSheet, RouterLink, DatePipe],
  templateUrl: './search.html',
  styleUrl: './search.scss',
})
export class Search implements OnInit {
  private readonly route = inject(ActivatedRoute);
  private readonly accommodations = inject(AccommodationService);
  private readonly router = inject(Router);
  private readonly seo = inject(SeoService);

  readonly criteria = signal<SearchCriteria>({});
  readonly results = signal<Accommodation[]>([]);
  readonly suggestions = signal<StaySuggestion[]>([]);
  readonly guests = signal<Guests>(NO_GUESTS);
  readonly filters = signal<SearchFilters>(NO_FILTERS);
  readonly sheetOpen = signal(false);

  readonly activeCount = computed(() => activeFilterCount(this.filters()));
  readonly chips = computed(() => filterChips(this.filters()));
  readonly sortOptions = SORT_OPTIONS;

  ngOnInit(): void {
    this.seo.apply({
      title: 'Rechercher un logement au CHM Montalivet et à Euronat',
      description:
        'Trouvez un mobil-home ou un bungalow libre sur vos dates, avec les disponibilités ' +
        'affichées directement dans les résultats.',
      path: '/recherche',
    });

    this.route.queryParamMap
      .pipe(
        tap((params) => {
          this.guests.set(guestsFromQuery(params));
          this.filters.set(filtersFromQuery(params));
        }),
        map((params): SearchCriteria => {
          const filters = this.filters();

          return {
            arrival: params.get('arrivee') ?? undefined,
            departure: params.get('depart') ?? undefined,
            guests: travellerCount(this.guests()) || undefined,
            districts: filters.districts,
            types: filters.types,
            bedrooms: filters.bedrooms || undefined,
            amenities: filters.amenities,
            order: filters.order ?? undefined,
          };
        }),
        tap((criteria) => this.criteria.set(criteria)),
        switchMap((criteria) =>
          this.accommodations.search(criteria).pipe(
            switchMap((found) => {
              const needsSuggestions =
                found.length === 0 && !!criteria.arrival && !!criteria.departure;

              const suggestions$ = needsSuggestions
                ? this.accommodations
                    .suggest(criteria)
                    .pipe(catchError(() => of<StaySuggestion[]>([])))
                : of<StaySuggestion[]>([]);

              return suggestions$.pipe(map((suggestions) => ({ found, suggestions })));
            }),
          ),
        ),
      )
      .subscribe(({ found, suggestions }) => {
        this.results.set(found);
        this.suggestions.set(suggestions);
      });
  }

  applyFilters(filters: SearchFilters): void {
    this.router.navigate(['/recherche'], {
      queryParams: filtersToQuery(filters),
      queryParamsHandling: 'merge',
      // Chaque clic dans le panneau ne doit pas ajouter une entrée à l'historique.
      replaceUrl: true,
    });
  }

  onOrderChange(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;
    const order = SORT_OPTIONS.find((option) => option.value === value)?.value ?? null;

    this.applyFilters({ ...this.filters(), order });
  }

  clearDistricts(): void {
    this.applyFilters({ ...this.filters(), districts: [] });
  }

  clearFilters(): void {
    this.applyFilters({ ...NO_FILTERS, order: this.filters().order });
  }
}
