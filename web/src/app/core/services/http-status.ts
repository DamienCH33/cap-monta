import { inject, Injectable, RESPONSE_INIT } from '@angular/core';

/**
 * Le code HTTP de la page rendue côté serveur. Une fiche supprimée qui répond 200 est une
 * « soft 404 » pour Google : elle reste indexée. Sans effet dans le navigateur.
 */
@Injectable({ providedIn: 'root' })
export class HttpStatus {
  private readonly response = inject(RESPONSE_INIT, { optional: true });

  set(status: 404 | 503): void {
    if (this.response) {
      this.response.status = status;
    }
  }
}
