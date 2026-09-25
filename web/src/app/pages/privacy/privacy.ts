import { Component, OnInit, inject } from '@angular/core';
import { RouterLink } from '@angular/router';

import { SeoService } from '../../core/services/seo';

@Component({
  selector: 'cm-privacy',
  imports: [RouterLink],
  templateUrl: './privacy.html',
  styleUrl: './privacy.scss',
})
export class Privacy implements OnInit {
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
