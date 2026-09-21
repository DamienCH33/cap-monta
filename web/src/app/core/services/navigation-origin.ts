import { Injectable } from '@angular/core';

/**
 * Se souvient qu'une fiche a été ouverte depuis « Mes logements », pour que son lien
 * de retour y ramène. En mémoire seulement : un rechargement de la page l'oublie, et
 * la fiche retombe sur son lien habituel.
 *
 * Plus robuste que l'état de navigation du routeur, qu'une navigation relancée par la
 * fiche elle-même (paramètres par défaut) peut écraser.
 */
@Injectable({ providedIn: 'root' })
export class NavigationOrigin {
  private ownerSpaceSlug: string | null = null;

  openedFromOwnerSpace(slug: string): void {
    this.ownerSpaceSlug = slug;
  }

  isFromOwnerSpace(slug: string | null): boolean {
    return null !== slug && this.ownerSpaceSlug === slug;
  }
}
