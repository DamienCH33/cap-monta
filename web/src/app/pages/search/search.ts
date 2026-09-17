import { DatePipe } from '@angular/common';
import { Component, inject, OnInit, signal } from '@angular/core';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { catchError, map, of, switchMap, tap } from 'rxjs';

import { Accommodation } from '../../core/models/accommodation';
import { SearchCriteria } from '../../core/models/search-criteria';
import { StaySuggestion } from '../../core/models/stay-suggestion';
import { AccommodationService } from '../../core/services/accommodation';
import { SeoService } from '../../core/services/seo';
import { AccommodationCard } from '../../shared/accommodation-card/accommodation-card';
import { SearchBar } from '../../shared/search-bar/search-bar';

@Component({
  selector: 'cm-search',
  imports: [SearchBar, AccommodationCard, RouterLink, DatePipe],
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
        map((params) => ({
          arrival: params.get('arrivee') ?? undefined,
          departure: params.get('depart') ?? undefined,
          guests: Number(params.get('voyageurs')) || undefined,
          district: params.get('quartier') ?? undefined,
        })),
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

  clearDistrict(): void {
    this.router.navigate(['/recherche'], {
      queryParams: { quartier: null },
      queryParamsHandling: 'merge',
    });
  }
}
