import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { map, Observable } from 'rxjs';

import { environment } from '../../../environments/environment';
import { Accommodation, JsonLdCollection } from '../models/accommodation';
import { SearchCriteria } from '../models/search-criteria';
import { Availability } from '../models/availability';
import { StaySuggestion } from '../models/stay-suggestion';

@Injectable({ providedIn: 'root' })
export class AccommodationService {
  private readonly http = inject(HttpClient);
  private readonly api = environment.apiUrl + '/api';

  search(criteria: SearchCriteria): Observable<Accommodation[]> {
    return this.http
      .get<JsonLdCollection<Accommodation>>(`${this.api}/accommodations`, {
        params: this.toParams(criteria),
        headers: { Accept: 'application/ld+json' },
      })
      .pipe(map((response) => response.member));
  }

  suggest(criteria: SearchCriteria): Observable<StaySuggestion[]> {
    let params = this.toParams({
      arrival: criteria.arrival,
      departure: criteria.departure,
      guests: criteria.guests,
      resort: criteria.resort,
    });

    const districts = criteria.districts ?? [];

    if (districts.length === 1) {
      params = params.set('district', districts[0]);
    }

    return this.http
      .get<JsonLdCollection<StaySuggestion>>(`${this.api}/stay-suggestions`, {
        params,
        headers: { Accept: 'application/ld+json' },
      })
      .pipe(map((response) => response.member));
  }
  getBySlug(slug: string): Observable<Accommodation> {
    return this.http.get<Accommodation>(`${this.api}/accommodations/${slug}`, {
      headers: { Accept: 'application/ld+json' },
    });
  }

  getAvailability(slug: string): Observable<Availability> {
    return this.http.get<Availability>(`${this.api}/accommodations/${slug}/availability`, {
      headers: { Accept: 'application/ld+json' },
    });
  }

  private toParams(criteria: SearchCriteria): HttpParams {
    let params = new HttpParams();

    if (criteria.arrival) {
      params = params.set('arrival', criteria.arrival);
    }
    if (criteria.departure) {
      params = params.set('departure', criteria.departure);
    }
    if (criteria.guests) {
      params = params.set('guests', criteria.guests);
    }
    if (criteria.resort) {
      params = params.set('resort', criteria.resort);
    }
    for (const district of criteria.districts ?? []) {
      params = params.append('district[]', district);
    }
    for (const type of criteria.types ?? []) {
      params = params.append('type[]', type);
    }
    if (criteria.bedrooms) {
      params = params.set('bedrooms', criteria.bedrooms);
    }
    for (const amenity of criteria.amenities ?? []) {
      params = params.append('amenities[]', amenity);
    }
    if (criteria.order) {
      params = params.set('order', criteria.order);
    }

    return params;
  }
}
