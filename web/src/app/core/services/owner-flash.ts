import { Injectable } from '@angular/core';

export interface Flash {
  /** Le logement concerné : le message s'affiche sur sa carte ou sur son formulaire. */
  slug: string;
  message: string;
  tone: 'ok' | 'error';
}

/**
 * Message flash de l'espace propriétaire : posé juste avant une navigation (« Brouillon
 * enregistré »), lu une seule fois par la page d'arrivée. En mémoire : un rechargement
 * de la page l'oublie, ce qui est le comportement attendu d'un message flash.
 */
@Injectable({ providedIn: 'root' })
export class OwnerFlash {
  private pending: Flash | null = null;

  set(flash: Flash): void {
    this.pending = flash;
  }

  /** Rend le message en attente et l'efface : il ne s'affiche qu'une fois. */
  take(): Flash | null {
    const flash = this.pending;
    this.pending = null;

    return flash;
  }
}
