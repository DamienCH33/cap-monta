import { Component, computed, inject, input } from '@angular/core';

import { Favorites } from '../../core/services/favorites';
import { Icon } from '../icon/icon';

/**
 * Le cœur « mettre de côté ». Deux états qui se distinguent par la forme (cœur plein ou vide)
 * et par le texte lu à l'écran, pas seulement par la couleur.
 */
@Component({
  selector: 'cm-favorite-button',
  imports: [Icon],
  template: `
    <button
      type="button"
      class="fav"
      [class.fav--on]="saved()"
      [class.fav--labelled]="labelled()"
      [attr.aria-pressed]="saved()"
      [attr.aria-label]="labelled() ? null : label()"
      [title]="label()"
      (click)="toggle($event)"
    >
      <cm-icon name="heart" />
      @if (labelled()) {
        <span>
          @if (saved()) {
            <ng-container i18n="@@fav.saved">Dans vos favoris</ng-container>
          } @else {
            <ng-container i18n="@@fav.add">Ajouter aux favoris</ng-container>
          }
        </span>
      }
    </button>
  `,
  styleUrl: './favorite-button.scss',
})
export class FavoriteButton {
  private readonly favorites = inject(Favorites);

  readonly slug = input.required<string>();
  /** Bouton avec texte (fiche) ou rond sur une photo (cartes). */
  readonly labelled = input(false);

  readonly saved = computed(() => this.favorites.list().includes(this.slug()));
  readonly label = computed(() =>
    this.saved()
      ? $localize`:@@fav.remove-label:Retirer de mes favoris`
      : $localize`:@@fav.add-label:Ajouter à mes favoris`,
  );

  toggle(event: Event): void {
    // Sur une carte, le bouton est posé sur le lien de la fiche : ne pas l'ouvrir.
    event.preventDefault();
    event.stopPropagation();
    this.favorites.toggle(this.slug());
  }
}
