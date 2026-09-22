import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { Observable } from 'rxjs';

import { environment } from '../../../environments/environment';
import { OwnerRates, RateInput } from '../models/owner-rates';

/** Les tarifs d'un logement du propriétaire connecté. Chaque réponse renvoie la grille entière. */
@Injectable({ providedIn: 'root' })
export class OwnerRatesService {
  private readonly http = inject(HttpClient);
  private readonly api = environment.apiUrl + '/api/owner/accommodations';

  get(slug: string): Observable<OwnerRates> {
    return this.http.get<OwnerRates>(this.url(slug), { withCredentials: true });
  }

  create(slug: string, input: RateInput): Observable<OwnerRates> {
    return this.http.post<OwnerRates>(this.url(slug), input, { withCredentials: true });
  }

  update(slug: string, id: string, input: RateInput): Observable<OwnerRates> {
    return this.http.put<OwnerRates>(`${this.url(slug)}/${id}`, input, { withCredentials: true });
  }

  remove(slug: string, id: string): Observable<OwnerRates> {
    return this.http.delete<OwnerRates>(`${this.url(slug)}/${id}`, { withCredentials: true });
  }

  copy(slug: string, fromYear: number): Observable<OwnerRates> {
    return this.http.post<OwnerRates>(
      `${this.url(slug)}/copy`,
      { fromYear },
      { withCredentials: true },
    );
  }

  private url(slug: string): string {
    return `${this.api}/${encodeURIComponent(slug)}/rates`;
  }
}
