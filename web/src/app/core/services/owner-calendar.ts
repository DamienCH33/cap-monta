import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { Observable } from 'rxjs';

import { environment } from '../../../environments/environment';
import { DayRange, OwnerCalendar } from '../models/owner-calendar';

/**
 * Le calendrier d'un logement du propriétaire connecté. Chaque réponse renvoie le
 * calendrier complet à jour : l'écran n'a jamais à recalculer ce que l'API a décidé.
 */
@Injectable({ providedIn: 'root' })
export class OwnerCalendarService {
  private readonly http = inject(HttpClient);
  private readonly api = environment.apiUrl + '/api/owner/accommodations';

  get(slug: string): Observable<OwnerCalendar> {
    return this.http.get<OwnerCalendar>(this.url(slug), { withCredentials: true });
  }

  block(slug: string, range: DayRange, note: string): Observable<OwnerCalendar> {
    return this.http.post<OwnerCalendar>(
      `${this.url(slug)}/blocks`,
      { start: range.start, end: range.end, note: note.trim() || null },
      { withCredentials: true },
    );
  }

  unblock(slug: string, id: string): Observable<OwnerCalendar> {
    return this.http.delete<OwnerCalendar>(`${this.url(slug)}/blocks/${id}`, {
      withCredentials: true,
    });
  }

  confirm(slug: string): Observable<OwnerCalendar> {
    return this.http.post<OwnerCalendar>(`${this.url(slug)}/confirm`, null, {
      withCredentials: true,
    });
  }

  private url(slug: string): string {
    return `${this.api}/${encodeURIComponent(slug)}/calendar`;
  }
}
