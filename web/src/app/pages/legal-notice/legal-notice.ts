import { Component, OnInit, inject } from '@angular/core';

import { SeoService } from '../../core/services/seo';

@Component({
  selector: 'cm-legal-notice',
  imports: [],
  templateUrl: './legal-notice.html',
  styleUrl: './legal-notice.scss',
})
export class LegalNotice implements OnInit {
  private readonly seo = inject(SeoService);

  ngOnInit(): void {
    this.seo.apply({
      title: 'Mentions légales',
      description: 'Éditeur, hébergeur et informations légales du site Cap Monta.',
      path: '/mentions-legales',
    });
  }
}
