import { Component, computed, input } from '@angular/core';

import { SitePhotoSlot, sitePhotoFallback, sitePhotoSrcset } from '../../core/models/site-photo';
import { SITE_PHOTOS } from '../../core/site-photos';

/**
 * Une photo du site (accueil, zones, bandeau propriétaires) en AVIF, avec WebP en repli, et la
 * bonne largeur choisie par le navigateur selon l'écran : nette sur un écran Retina, légère sur
 * un téléphone. N'affiche rien si l'emplacement n'a pas encore de photo.
 */
@Component({
  selector: 'cm-site-photo',
  template: `
    @if (photo(); as current) {
      <picture>
        <source type="image/avif" [attr.srcset]="avif()" [attr.sizes]="sizes()" />
        <source type="image/webp" [attr.srcset]="webp()" [attr.sizes]="sizes()" />
        <img
          [src]="fallback()"
          [alt]="decorative() ? '' : current.alt"
          [attr.width]="1000"
          [attr.height]="height()"
          [attr.loading]="eager() ? 'eager' : 'lazy'"
          [attr.fetchpriority]="eager() ? 'high' : null"
          decoding="async"
          [style.object-position]="current.focus"
        />
      </picture>
    }
  `,
  styles: [
    ':host{display:block}:host:empty{display:none}picture,img{display:block;width:100%;height:100%}img{object-fit:cover}',
  ],
})
export class SitePhoto {
  readonly slot = input.required<SitePhotoSlot>();
  /** Largeur affichée, au format de l'attribut sizes (« 100vw », « (max-width: 720px) 100vw, 33vw »). */
  readonly sizes = input('100vw');
  /** La photo du haut de page se charge tout de suite ; les autres quand on s'en approche. */
  readonly eager = input(false);
  /** Photo d'ambiance que le texte à côté décrit déjà : pas de texte alternatif. */
  readonly decorative = input(false);

  readonly photo = computed(() => SITE_PHOTOS[this.slot()] ?? null);
  readonly avif = computed(() => this.srcset('avif'));
  readonly webp = computed(() => this.srcset('webp'));
  readonly fallback = computed(() => {
    const photo = this.photo();

    return photo ? sitePhotoFallback(this.slot(), photo) : '';
  });
  readonly height = computed(() => Math.round(1000 / (this.photo()?.ratio ?? 1)));

  private srcset(format: 'avif' | 'webp'): string {
    const photo = this.photo();

    return photo ? sitePhotoSrcset(this.slot(), photo, format) : '';
  }
}
