import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { map, Observable } from 'rxjs';

import { environment } from '../../../environments/environment';
import { JsonLdCollection } from '../models/accommodation';
import {
  AccommodationChanges,
  NewAccommodation,
  OwnerAccommodation,
} from '../models/owner-accommodation';

/**
 * Les logements du propriétaire connecté. Chaque appel envoie le cookie de session :
 * sans lui, l'API répond 401.
 */
@Injectable({ providedIn: 'root' })
export class OwnerAccommodationService {
  private readonly http = inject(HttpClient);
  private readonly api = environment.apiUrl + '/api/owner/accommodations';

  list(): Observable<OwnerAccommodation[]> {
    return this.http
      .get<JsonLdCollection<OwnerAccommodation>>(this.api, {
        headers: { Accept: 'application/ld+json' },
        withCredentials: true,
      })
      .pipe(map((response) => response.member));
  }

  get(slug: string): Observable<OwnerAccommodation> {
    return this.http.get<OwnerAccommodation>(this.url(slug), {
      headers: { Accept: 'application/ld+json' },
      withCredentials: true,
    });
  }

  create(payload: NewAccommodation): Observable<OwnerAccommodation> {
    return this.http.post<OwnerAccommodation>(this.api, payload, {
      headers: { Accept: 'application/ld+json', 'Content-Type': 'application/ld+json' },
      withCredentials: true,
    });
  }

  /** N'envoyer que ce qui a changé : un champ absent n'est pas touché par l'API. */
  update(slug: string, changes: AccommodationChanges): Observable<OwnerAccommodation> {
    return this.http.patch<OwnerAccommodation>(this.url(slug), changes, {
      headers: { Accept: 'application/ld+json', 'Content-Type': 'application/merge-patch+json' },
      withCredentials: true,
    });
  }

  publish(slug: string): Observable<OwnerAccommodation> {
    return this.transition(slug, 'publish');
  }

  archive(slug: string): Observable<OwnerAccommodation> {
    return this.transition(slug, 'archive');
  }

  private transition(slug: string, action: 'publish' | 'archive'): Observable<OwnerAccommodation> {
    return this.http.post<OwnerAccommodation>(`${this.url(slug)}/${action}`, null, {
      headers: { Accept: 'application/ld+json' },
      withCredentials: true,
    });
  }

  private url(slug: string): string {
    return `${this.api}/${encodeURIComponent(slug)}`;
  }
}
