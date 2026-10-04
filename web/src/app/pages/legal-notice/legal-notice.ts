import { Component, OnInit, inject } from '@angular/core';

import { SitePhotoSlot } from '../../core/models/site-photo';
import { currentLang } from '../../core/i18n/lang';
import { SeoService } from '../../core/services/seo';
import { SITE_PHOTOS } from '../../core/site-photos';

@Component({
  selector: 'cm-legal-notice',
  imports: [],
  templateUrl: './legal-notice.html',
  styleUrl: './legal-notice.scss',
})
export class LegalNotice implements OnInit {
  /** Ces pages ne sont qu'en français : ailleurs, un mot pour le dire dans la langue du visiteur. */
  readonly french = 'fr' === currentLang();
  readonly lang = currentLang();

  private readonly seo = inject(SeoService);

  readonly photoCredits = (Object.keys(SITE_PHOTOS) as SitePhotoSlot[])
    .map((slot) => ({ slot, ...SITE_PHOTOS[slot]! }))
    .filter((photo) => null !== photo.credit);

  ngOnInit(): void {
    this.seo.apply({
      title: 'Mentions légales',
      description: 'Éditeur, hébergeur et informations légales du site Cap Monta.',
      path: '/mentions-legales',
      frenchOnly: true,
    });
  }
}
