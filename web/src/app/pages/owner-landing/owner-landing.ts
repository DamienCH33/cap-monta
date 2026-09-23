import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { RouterLink } from '@angular/router';

import { SeoService } from '../../core/services/seo';

@Component({
  selector: 'cm-owner-landing',
  imports: [RouterLink],
  templateUrl: './owner-landing.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class OwnerLanding {
  constructor() {
    const seo = inject(SeoService);

    seo.apply({
      title: 'Louer son mobil-home au CHM Montalivet ou à Euronat',
      description:
        'Publiez gratuitement votre bungalow, mobil-home, caravane, chalet ou studio. Aucune commission, ' +
        'un calendrier à jour, et vous gardez la main sur vos tarifs et vos réponses.',
      path: '/proprietaire',
    });

    seo.setJsonLd({
      '@context': 'https://schema.org',
      '@type': 'FAQPage',
      mainEntity: [
        {
          '@type': 'Question',
          name: "Combien coûte la publication d'une annonce ?",
          acceptedAnswer: {
            '@type': 'Answer',
            text: 'Rien. Cap Monta ne prélève aucune commission sur vos locations.',
          },
        },
        {
          '@type': 'Question',
          name: 'Faut-il être propriétaire au CHM Montalivet ou à Euronat ?',
          acceptedAnswer: {
            '@type': 'Answer',
            text: 'Oui. Cap Monta ne référence que les logements situés dans ces deux domaines.',
          },
        },
        {
          '@type': 'Question',
          name: 'Puis-je publier plusieurs logements ?',
          acceptedAnswer: {
            '@type': 'Answer',
            text: 'Oui, un même compte peut gérer plusieurs logements et leurs calendriers.',
          },
        },
      ],
    });
  }
}
