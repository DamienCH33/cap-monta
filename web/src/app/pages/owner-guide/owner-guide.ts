import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { RouterLink } from '@angular/router';

import { SeoService } from '../../core/services/seo';

/**
 * Le guide pas à pas du propriétaire, du compte à la publication. Les noms de boutons sont
 * ceux de l'espace propriétaire : si un libellé change là-bas, il change ici aussi.
 */
@Component({
  selector: 'cm-owner-guide',
  imports: [RouterLink],
  templateUrl: './owner-guide.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class OwnerGuide {
  constructor() {
    inject(SeoService).apply({
      title: 'Publier son logement pas à pas',
      description:
        'Du compte à la mise en ligne en 10 minutes : importer votre annonce existante, vérifier ' +
        'tarifs et dates, ajouter vos photos, publier. Et que faire si un message vous bloque.',
      path: '/proprietaire/guide',
      frenchOnly: true,
    });
  }
}
