import { Component, OnInit, inject } from '@angular/core';
import { RouterLink } from '@angular/router';

import { currentLang } from '../../core/i18n/lang';
import { SeoService } from '../../core/services/seo';

@Component({
  selector: 'cm-terms',
  imports: [RouterLink],
  templateUrl: './terms.html',
  styleUrl: './terms.scss',
})
export class Terms implements OnInit {
  /** Ces pages ne sont qu'en français : ailleurs, un mot pour le dire dans la langue du visiteur. */
  readonly french = 'fr' === currentLang();
  readonly lang = currentLang();

  private readonly seo = inject(SeoService);

  ngOnInit(): void {
    this.seo.apply({
      title: "Conditions générales d'utilisation",
      description:
        "Règles d'utilisation de Cap Monta : rôle de mise en relation, obligations des " +
        'propriétaires et des locataires, responsabilité.',
      path: '/conditions-generales',
      frenchOnly: true,
    });
  }
}
