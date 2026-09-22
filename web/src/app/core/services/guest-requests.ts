import { isPlatformBrowser } from '@angular/common';
import { inject, Injectable, PLATFORM_ID, signal } from '@angular/core';

/** Une demande retenue sur cet appareil : de quoi l'afficher et y revenir. */
export interface RememberedRequest {
  token: string;
  title: string;
  start: string;
  end: string;
}

const KEY = 'cap-monta.demandes';

/**
 * Les demandes envoyées depuis ce navigateur, pour le lien « Mes demandes » de l'en-tête.
 *
 * Un confort, pas une source de vérité : la clé reste l'email reçu, et la page « Mes
 * demandes » renvoie les liens par email. Rien n'est gardé côté serveur ; le stockage peut
 * être vide ou refusé (navigation privée) : tout est entouré de try/catch.
 * Les séjours terminés sont oubliés d'eux-mêmes.
 */
@Injectable({ providedIn: 'root' })
export class GuestRequests {
  private readonly isBrowser = isPlatformBrowser(inject(PLATFORM_ID));
  private readonly items = signal<RememberedRequest[]>(this.read());

  readonly list = this.items.asReadonly();

  remember(request: RememberedRequest): void {
    this.save([request, ...this.items().filter((item) => item.token !== request.token)]);
  }

  forget(token: string): void {
    this.save(this.items().filter((item) => item.token !== token));
  }

  forgetAll(): void {
    this.save([]);
  }

  private read(): RememberedRequest[] {
    if (!this.isBrowser) {
      return [];
    }

    try {
      const stored: unknown = JSON.parse(localStorage.getItem(KEY) ?? '[]');
      const today = new Date().toISOString().slice(0, 10);

      return Array.isArray(stored)
        ? (stored as RememberedRequest[]).filter(
            (item) => /^[0-9a-f]{48}$/.test(item?.token) && item.end > today,
          )
        : [];
    } catch {
      return [];
    }
  }

  private save(items: RememberedRequest[]): void {
    this.items.set(items);

    if (!this.isBrowser) {
      return;
    }

    try {
      localStorage.setItem(KEY, JSON.stringify(items.slice(0, 20)));
    } catch {
      // Stockage plein ou interdit : la liste vaut pour cette visite seulement.
    }
  }
}
