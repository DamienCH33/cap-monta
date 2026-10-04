import { Component, OnInit, inject } from '@angular/core';
import { RouterLink } from '@angular/router';

import { currentLang } from '../../core/i18n/lang';
import { SeoService } from '../../core/services/seo';

@Component({
  selector: 'cm-privacy',
  imports: [RouterLink],
  templateUrl: './privacy.html',
  styleUrl: './privacy.scss',
})
export class Privacy implements OnInit {
  /** Ces pages ne sont qu'en français : ailleurs, un mot pour le dire dans la langue du visiteur. */
  readonly french = 'fr' === currentLang();
  readonly lang = currentLang();

  private readonly seo = inject(SeoService);

  ngOnInit(): void {
    this.seo.apply({
      title: 'Politique de confidentialité',
      description:
        'Quelles données Cap Monta collecte, pourquoi, combien de temps elles sont conservées ' +
        'et comment exercer vos droits.',
      path: '/confidentialite',
      frenchOnly: true,
    });
  }
}
