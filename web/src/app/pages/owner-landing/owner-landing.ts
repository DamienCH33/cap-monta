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
      title: $localize`:@@owner.landing.seo-title:Louer son mobil-home au CHM Montalivet ou à Euronat`,
      description: $localize`:@@owner.landing.seo-description:Publiez gratuitement votre bungalow, mobil-home, caravane, chalet ou studio. Aucune commission, un calendrier à jour, et vous gardez la main sur vos tarifs et vos réponses.`,
      path: '/proprietaire',
    });

    seo.setJsonLd({
      '@context': 'https://schema.org',
      '@type': 'FAQPage',
      mainEntity: [
        {
          '@type': 'Question',
          name: $localize`:@@owner.landing.faq-cost-q: Combien coûte la publication d'une annonce ? `.trim(),
          acceptedAnswer: {
            '@type': 'Answer',
            text: $localize`:@@owner.landing.faq-cost-a: Rien. Aucun abonnement, aucune commission sur vos locations. `.trim(),
          },
        },
        {
          '@type': 'Question',
          name: $localize`:@@owner.landing.faq-domains-q: Faut-il être propriétaire au CHM Montalivet ou à Euronat ? `.trim(),
          acceptedAnswer: {
            '@type': 'Answer',
            text: $localize`:@@owner.landing.faq-domains-a: Oui. Cap Monta ne référence que ces deux domaines, et c'est volontaire : un site qui connaît les quartiers, les distances à la plage et les usages du lieu est plus utile qu'un annuaire généraliste. `.trim(),
          },
        },
        {
          '@type': 'Question',
          name: $localize`:@@owner.landing.faq-several-q: Puis-je publier plusieurs logements ? `.trim(),
          acceptedAnswer: {
            '@type': 'Answer',
            text: $localize`:@@owner.landing.faq-several-a: Oui. Un même compte gère autant de logements et de calendriers que nécessaire. `.trim(),
          },
        },
      ],
    });
  }
}
