import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { map, Observable } from 'rxjs';

import { environment } from '../../../environments/environment';
import { JsonLdCollection } from '../models/accommodation';
import { District } from '../models/district';

@Injectable({ providedIn: 'root' })
export class DistrictService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/api`;

  list(): Observable<District[]> {
    return this.http
      .get<JsonLdCollection<District>>(`${this.baseUrl}/districts`, {
        headers: { Accept: 'application/ld+json' },
      })
      .pipe(map((response) => response.member));
  }

  get(slug: string): Observable<District> {
    return this.http.get<District>(`${this.baseUrl}/districts/${encodeURIComponent(slug)}`, {
      headers: { Accept: 'application/ld+json' },
    });
  }
}
