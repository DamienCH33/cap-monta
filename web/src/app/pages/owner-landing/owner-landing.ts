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
      frenchOnly: true,
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
            text: 'Rien. Aucun abonnement, aucune commission sur vos locations.',
          },
        },
        {
          '@type': 'Question',
          name: 'Faut-il être propriétaire au CHM Montalivet ou à Euronat ?',
          acceptedAnswer: {
            '@type': 'Answer',
            text: "Oui. Cap Monta ne référence que ces deux domaines, et c'est volontaire : un site qui connaît les quartiers, les distances à la plage et les usages du lieu est plus utile qu'un annuaire généraliste.",
          },
        },
        {
          '@type': 'Question',
          name: 'Puis-je publier plusieurs logements ?',
          acceptedAnswer: {
            '@type': 'Answer',
            text: 'Oui. Un même compte gère autant de logements et de calendriers que nécessaire.',
          },
        },
      ],
    });
  }
}
