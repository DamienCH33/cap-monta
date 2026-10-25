import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { Observable, tap } from 'rxjs';

import { environment } from '../../../environments/environment';
import { Analytics } from './analytics';

export interface NewStayAlert {
  email: string;
  arrival: string;
  departure: string;
  guests: number;
  resort: string | null;
  consent: boolean;
}

/** Ce que la page de l'alerte affiche : la recherche, jamais l'adresse email. */
export interface StayAlertView {
  arrival: string;
  departure: string;
  guests: number;
  resort: string | null;
  confirmed: boolean;
}

/**
 * « Prévenez-moi » : le voyageur qui ne trouve rien laisse son email. L'alerte ne vit qu'après
 * le clic dans l'email d'activation ; ses liens portent une clé signée par l'API.
 */
@Injectable({ providedIn: 'root' })
export class StayAlertService {
  private readonly http = inject(HttpClient);
  private readonly analytics = inject(Analytics);
  private readonly baseUrl = `${environment.apiUrl}/api/stay-alerts`;

  create(alert: NewStayAlert): Observable<void> {
    return this.http
      .post<void>(this.baseUrl, alert)
      .pipe(tap(() => this.analytics.event('alerte-creee')));
  }

  show(key: string): Observable<StayAlertView> {
    return this.http.get<StayAlertView>(`${this.baseUrl}/${encodeURIComponent(key)}`);
  }

  confirm(key: string): Observable<StayAlertView> {
    return this.http
      .post<StayAlertView>(`${this.baseUrl}/${encodeURIComponent(key)}/confirm`, null)
      .pipe(tap(() => this.analytics.event('alerte-activee')));
  }

  stop(key: string): Observable<void> {
    return this.http.delete<void>(`${this.baseUrl}/${encodeURIComponent(key)}`);
  }
}
