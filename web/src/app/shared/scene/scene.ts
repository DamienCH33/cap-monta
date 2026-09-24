import { Component, input } from '@angular/core';

export type SceneKind =
  | 'bungalow'
  | 'mobile_home'
  | 'caravan'
  | 'chalet'
  | 'studio'
  | 'dunes'
  | 'central'
  | 'roadside'
  | 'owner';

/**
 * Petites scènes dessinées (océan, dune, pins, logements) qui tiennent la place d'une photo :
 * une carte sans photo du propriétaire, les trois zones du domaine, le bloc propriétaires.
 * Du SVG en ligne : rien à télécharger, net à toutes les tailles.
 */
@Component({
  selector: 'cm-scene',
  templateUrl: './scene.html',
  styles: [':host{display:block}svg{display:block;width:100%;height:100%}'],
})
export class Scene {
  // Un type de logement ou une zone ; tout le reste donne le bungalow.
  readonly kind = input.required<SceneKind | string>();
}
