import { isPlatformBrowser } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { computed, inject, Injectable, PLATFORM_ID, signal } from '@angular/core';
import { catchError, Observable, of, tap } from 'rxjs';

import { environment } from '../../../environments/environment';
import { Owner } from '../models/owner';

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly http = inject(HttpClient);
  private readonly api = environment.apiUrl + '/api';
  private readonly isBrowser = isPlatformBrowser(inject(PLATFORM_ID));

  private readonly owner = signal<Owner | null>(null);
  private readonly checked = signal(false);

  readonly currentOwner = this.owner.asReadonly();
  readonly isLoggedIn = computed(() => null !== this.owner());

  readonly sessionChecked = this.checked.asReadonly();

  login(email: string, password: string): Observable<Owner> {
    return this.http
      .post<Owner>(`${this.api}/login`, { email, password }, { withCredentials: true })
      .pipe(tap((owner) => this.remember(owner)));
  }

  logout(): Observable<void> {
    return this.http
      .post<void>(`${this.api}/logout`, {}, { withCredentials: true })
      .pipe(tap(() => this.remember(null)));
  }

  /**
   * Reprend une session existante au chargement de l'application.
   */
  restore(): Observable<Owner | null> {
    if (!this.isBrowser || this.checked()) {
      return of(this.owner());
    }

    return this.http.get<Owner>(`${this.api}/owner/me`, { withCredentials: true }).pipe(
      // Un 401 n'est pas une erreur ici : c'est la réponse « personne n'est connecté ».
      catchError(() => of(null)),
      tap((owner) => this.remember(owner)),
    );
  }

  private remember(owner: Owner | null): void {
    this.owner.set(owner);
    this.checked.set(true);
  }

  register(payload: { email: string; displayName: string; password: string }): Observable<void> {
    return this.http.post<void>(`${this.api}/register`, payload, { withCredentials: true });
  }

  askPasswordReset(email: string): Observable<void> {
    return this.http.post<void>(
      `${this.api}/password/forgotten`,
      { email },
      { withCredentials: true },
    );
  }

  resetPassword(jeton: string, password: string): Observable<void> {
    return this.http.post<void>(
      `${this.api}/password/reset`,
      { jeton, password },
      { withCredentials: true },
    );
  }
}
