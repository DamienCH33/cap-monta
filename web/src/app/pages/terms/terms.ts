import { Component, OnInit, inject } from '@angular/core';
import { RouterLink } from '@angular/router';

import { SeoService } from '../../core/services/seo';

@Component({
  selector: 'cm-terms',
  imports: [RouterLink],
  templateUrl: './terms.html',
  styleUrl: './terms.scss',
})
export class Terms implements OnInit {
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
