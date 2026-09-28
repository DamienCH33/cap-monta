import { isPlatformBrowser } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { computed, inject, Injectable, PLATFORM_ID, signal } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { catchError, finalize, Observable, of, shareReplay, tap, throwError } from 'rxjs';

import { environment } from '../../../environments/environment';
import { Owner } from '../models/owner';
import { Analytics } from './analytics';

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly http = inject(HttpClient);
  private readonly analytics = inject(Analytics);
  private readonly api = environment.apiUrl + '/api';
  private readonly isBrowser = isPlatformBrowser(inject(PLATFORM_ID));

  private readonly owner = signal<Owner | null>(null);
  private readonly checked = signal(false);

  readonly currentOwner = this.owner.asReadonly();
  readonly isLoggedIn = computed(() => null !== this.owner());

  /**
   * L'en-tête sait s'il doit afficher « Se connecter » : session vérifiée auprès de l'API, ou
   * navigateur qui ne s'est jamais connecté (restore({ ifKnown: true }) sans appel).
   */
  private readonly probed = signal(false);
  readonly sessionChecked = computed(() => this.checked() || this.probed());

  login(email: string, password: string): Observable<Owner> {
    return this.http
      .post<Owner>(`${this.api}/login`, { email, password }, { withCredentials: true })
      .pipe(tap((owner) => this.remember(owner)));
  }

  /** Même si l'appel échoue (session déjà expirée, réseau), l'interface se déconnecte. */
  logout(): Observable<void> {
    return this.http
      .post<void>(`${this.api}/logout`, {}, { withCredentials: true })
      .pipe(finalize(() => this.remember(null)));
  }

  /** La session a expiré côté serveur : on l'oublie ici aussi. */
  forget(): void {
    this.remember(null);
  }

  /** L'en-tête et la garde demandent la session en même temps : un seul appel. */
  private pending: Observable<Owner | null> | null = null;

  /**
   * Reprend une session existante au chargement de l'application.
   *
   * `ifKnown` : seulement si ce navigateur s'est déjà connecté. L'en-tête s'en sert pour ne
   * pas interroger l'API (et recevoir un 401) à chaque visite d'un voyageur ; la garde de
   * l'espace propriétaire, elle, vérifie toujours.
   */
  restore(options: { ifKnown?: boolean } = {}): Observable<Owner | null> {
    if (!this.isBrowser || this.checked()) {
      return of(this.owner());
    }
    if (options.ifKnown && !this.hint()) {
      // Personne ne s'est connecté ici : « Se connecter » peut s'afficher. La garde de l'espace
      // propriétaire, elle, interrogera quand même l'API (checked reste faux).
      this.probed.set(true);

      return of(null);
    }

    this.pending ??= this.http.get<Owner>(`${this.api}/owner/me`, { withCredentials: true }).pipe(
      // Un 401 n'est pas une erreur ici : c'est la réponse « personne n'est connecté ».
      // Une autre erreur (API injoignable) ne dit rien de la session : on ne la note pas,
      // et on réessaiera à la prochaine navigation.
      catchError((error: HttpErrorResponse) =>
        401 === error.status || 403 === error.status ? of(null) : throwError(() => error),
      ),
      tap((owner) => this.remember(owner)),
      finalize(() => (this.pending = null)),
      shareReplay(1),
    );

    return this.pending;
  }

  /** Nom affiché et téléphone : la session garde la version renvoyée par l'API. */
  updateProfile(payload: { displayName: string; phone: string }): Observable<Owner> {
    return this.http
      .patch<Owner>(`${this.api}/owner/me`, payload, { withCredentials: true })
      .pipe(tap((owner) => this.remember(owner)));
  }

  /** Ferme le compte pour de bon. La session disparaît avec lui. */
  deleteAccount(password: string): Observable<void> {
    return this.http
      .delete<void>(`${this.api}/owner/me`, { body: { password }, withCredentials: true })
      .pipe(tap(() => this.remember(null)));
  }

  changePassword(currentPassword: string, newPassword: string): Observable<void> {
    return this.http.post<void>(
      `${this.api}/owner/me/password`,
      { currentPassword, newPassword },
      { withCredentials: true },
    );
  }

  private remember(owner: Owner | null): void {
    this.owner.set(owner);
    this.checked.set(true);
    this.hint(null !== owner);
  }

  /** Une simple marque « déjà connecté ici », sans rien de la session elle-même. */
  private hint(set?: boolean): boolean {
    if (!this.isBrowser) {
      return false;
    }
    try {
      if (true === set) {
        localStorage.setItem(AuthService.HINT, '1');
      } else if (false === set) {
        localStorage.removeItem(AuthService.HINT);
      }

      return '1' === localStorage.getItem(AuthService.HINT);
    } catch {
      // Stockage bloqué (navigation privée stricte) : on interroge l'API comme avant.
      return true;
    }
  }

  private static readonly HINT = 'cm.owner-session';

  register(payload: { email: string; displayName: string; password: string }): Observable<void> {
    return this.http
      .post<void>(`${this.api}/register`, payload, { withCredentials: true })
      .pipe(tap(() => this.analytics.event('inscription-proprietaire')));
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
