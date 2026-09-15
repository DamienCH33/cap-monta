import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { map, Observable } from 'rxjs';

import { environment } from '../../../environments/environment';
import { Accommodation, JsonLdCollection } from '../models/accommodation';
import { SearchCriteria } from '../models/search-criteria';

@Injectable({ providedIn: 'root' })
export class AccommodationService {
  private readonly http = inject(HttpClient);
  private readonly api = environment.apiUrl + '/api';

  search(criteria: SearchCriteria): Observable<Accommodation[]> {
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

    return this.http
      .get<JsonLdCollection<Accommodation>>(`${this.api}/accommodations`, {
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
}
