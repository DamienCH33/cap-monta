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
import { District } from '../../core/models/district';
import { DistrictService } from '../../core/services/district';
import { Pagination } from '../../shared/pagination/pagination';

@Component({
  selector: 'cm-search',
  imports: [SearchBar, AccommodationCard, FilterSheet, RouterLink, DatePipe, Pagination],
  templateUrl: './search.html',
  styleUrl: './search.scss',
})
export class Search implements OnInit {
  private readonly route = inject(ActivatedRoute);
  private readonly accommodations = inject(AccommodationService);
  private readonly router = inject(Router);
  private readonly seo = inject(SeoService);
  private readonly districtApi = inject(DistrictService);

  readonly criteria = signal<SearchCriteria>({});
  readonly results = signal<Accommodation[]>([]);
  readonly suggestions = signal<StaySuggestion[]>([]);
  readonly guests = signal<Guests>(NO_GUESTS);
  readonly filters = signal<SearchFilters>(NO_FILTERS);
  readonly sheetOpen = signal(false);

  readonly activeCount = computed(() => activeFilterCount(this.filters()));
  readonly chips = computed(() => {
    const names = new Map<string, string>();

    for (const district of this.districts()) {
      names.set(district.slug, district.name);
      names.set(district.name, district.name);
    }

    return filterChips(this.filters(), (value) => names.get(value) ?? value);
  });
  readonly sortOptions = SORT_OPTIONS;
  readonly districts = signal<District[]>([]);
  readonly total = signal(0);
  readonly page = signal(1);
  readonly totalPages = signal(1);

  ngOnInit(): void {
    this.seo.apply({
      title: 'Rechercher un logement au CHM Montalivet et à Euronat',
      description:
        'Trouvez un mobil-home ou un bungalow libre sur vos dates, avec les disponibilités ' +
        'affichées directement dans les résultats.',
      path: '/recherche',
    });

    this.districtApi.list().subscribe((found) => this.districts.set(found));
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
            page: Number(params.get('page')) || 1,
          };
        }),
        tap((criteria) => this.criteria.set(criteria)),
        switchMap((criteria) =>
          this.accommodations.searchPage(criteria).pipe(
            switchMap((found) => {
              const needsSuggestions =
                found.items.length === 0 && !!criteria.arrival && !!criteria.departure;

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
        this.results.set(found.items);
        this.total.set(found.total);
        this.page.set(found.page);
        this.totalPages.set(found.totalPages);
        this.suggestions.set(suggestions);
      });
  }

  applyFilters(filters: SearchFilters): void {
    this.router.navigate(['/recherche'], {
      queryParams: { ...filtersToQuery(filters), page: null },
      queryParamsHandling: 'merge',
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
