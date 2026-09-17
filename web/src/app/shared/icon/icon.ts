import { Component, computed, inject, input } from '@angular/core';
import { DomSanitizer } from '@angular/platform-browser';

import { IconName, ICONS } from './icons';

/**
 * Icône SVG en ligne. Elle prend la taille du texte (1em) et sa couleur (currentColor),
 * comme la fonte qu'elle remplace : les styles existants continuent de s'appliquer.
 */
@Component({
  selector: 'cm-icon',
  template: `<svg
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 24 24"
    width="1em"
    height="1em"
    fill="none"
    stroke="currentColor"
    [attr.stroke-width]="stroke()"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
    focusable="false"
    [innerHTML]="paths()"
  ></svg>`,
  styles: `
    :host {
      display: inline-flex;
      line-height: 0;
      vertical-align: -0.125em;
    }
  `,
})
export class Icon {
  private readonly sanitizer = inject(DomSanitizer);

  readonly name = input.required<IconName>();
  readonly stroke = input(2);

  // Les tracés viennent d'une constante du code, jamais d'une saisie : on peut les déclarer sûrs.
  readonly paths = computed(() => this.sanitizer.bypassSecurityTrustHtml(ICONS[this.name()]));
}
