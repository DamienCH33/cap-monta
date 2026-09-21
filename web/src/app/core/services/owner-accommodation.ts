import { HttpClient, HttpEvent } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { map, Observable } from 'rxjs';

import { environment } from '../../../environments/environment';
import { JsonLdCollection } from '../models/accommodation';
import {
  AccommodationChanges,
  DistrictOption,
  NewAccommodation,
  OwnerAccommodation,
  OwnerPhoto,
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

  /**
   * Envoie une photo. Rend les événements de progression, pour afficher une barre :
   * utile au téléphone, où une photo met plusieurs secondes à partir.
   */
  uploadPhoto(slug: string, file: File): Observable<HttpEvent<OwnerPhoto>> {
    const body = new FormData();
    body.append('photo', file);
    // Case cochée par le propriétaire avant l'envoi : aucune personne sur la photo.
    body.append('noPeople', '1');

    return this.http.post<OwnerPhoto>(`${this.url(slug)}/photos`, body, {
      withCredentials: true,
      reportProgress: true,
      observe: 'events',
    });
  }

  deletePhoto(slug: string, id: string): Observable<void> {
    return this.http.delete<void>(`${this.url(slug)}/photos/${encodeURIComponent(id)}`, {
      withCredentials: true,
    });
  }

  /** Le nouvel ordre complet : la première photo devient la couverture. */
  reorderPhotos(slug: string, ids: string[]): Observable<OwnerPhoto[]> {
    return this.http.put<OwnerPhoto[]>(`${this.url(slug)}/photos/order`, { ids }, {
      withCredentials: true,
    });
  }

  /** Les quartiers, pour les listes déroulantes des formulaires. Liste publique. */
  districts(): Observable<DistrictOption[]> {
    return this.http
      .get<JsonLdCollection<DistrictOption>>(`${environment.apiUrl}/api/districts`, {
        headers: { Accept: 'application/ld+json' },
      })
      .pipe(map((response) => response.member));
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
