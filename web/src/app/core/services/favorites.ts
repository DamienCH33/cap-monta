import { isPlatformBrowser } from '@angular/common';
import { computed, inject, Injectable, PLATFORM_ID, signal } from '@angular/core';

const KEY = 'cap-monta.favoris';
const MAX = 50;
const SLUG = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;

/**
 * Les logements mis de côté par le visiteur, sur cet appareil, sans compte.
 *
 * Même principe que « Mes demandes » : un confort gardé dans le navigateur (vide en navigation
 * privée ou si le stockage est refusé, d'où les try/catch), rien côté serveur. Pour passer d'un
 * appareil à l'autre ou montrer sa sélection à sa famille, la page Favoris fabrique un lien
 * qui contient la liste.
 */
@Injectable({ providedIn: 'root' })
export class Favorites {
  private readonly isBrowser = isPlatformBrowser(inject(PLATFORM_ID));
  private readonly slugs = signal<string[]>(this.read());

  readonly list = this.slugs.asReadonly();
  readonly count = computed(() => this.slugs().length);

  has(slug: string): boolean {
    return this.slugs().includes(slug);
  }

  toggle(slug: string): void {
    this.save(this.has(slug) ? this.slugs().filter((s) => s !== slug) : [slug, ...this.slugs()]);
  }

  remove(slug: string): void {
    this.save(this.slugs().filter((s) => s !== slug));
  }

  /** Ajoute une sélection reçue par lien, sans doublon, en gardant l'ordre reçu. */
  addAll(slugs: string[]): void {
    const incoming = slugs.filter((slug) => SLUG.test(slug));
    this.save([...incoming, ...this.slugs().filter((s) => !incoming.includes(s))]);
  }

  private read(): string[] {
    if (!this.isBrowser) {
      return [];
    }

    try {
      const stored: unknown = JSON.parse(localStorage.getItem(KEY) ?? '[]');

      return Array.isArray(stored)
        ? stored.filter((slug): slug is string => 'string' === typeof slug && SLUG.test(slug))
        : [];
    } catch {
      return [];
    }
  }

  private save(slugs: string[]): void {
    const kept = slugs.slice(0, MAX);
    this.slugs.set(kept);

    if (!this.isBrowser) {
      return;
    }

    try {
      localStorage.setItem(KEY, JSON.stringify(kept));
    } catch {
      // Stockage plein ou interdit : la liste vaut pour cette visite seulement.
    }
  }
}
