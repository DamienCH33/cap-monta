import { Component, computed, input } from '@angular/core';

import { IconName, ICONS } from './icons';

/**
 * Icône SVG en ligne. Elle prend la taille du texte (1em) et sa couleur (currentColor),
 * comme la fonte qu'elle remplace. Les tracés sont créés par le template, sans innerHTML,
 * pour que le rendu serveur fonctionne.
 */
@Component({
  selector: 'cm-icon',
  template: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="1em" height="1em" fill="none" stroke="currentColor" [attr.stroke-width]="stroke()" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">@for (d of paths(); track $index) {<path [attr.d]="d" />}</svg>`,
  styles: `
    :host {
      display: inline-flex;
      line-height: 0;
      vertical-align: -0.125em;
    }
  `,
})
export class Icon {
  readonly name = input.required<IconName>();
  readonly stroke = input(2);

  readonly paths = computed(() => ICONS[this.name()]);
}
