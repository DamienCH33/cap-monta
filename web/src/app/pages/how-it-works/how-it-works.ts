import { Component, OnInit, inject } from '@angular/core';
import { RouterLink } from '@angular/router';

import { SeoService } from '../../core/services/seo';

@Component({
  selector: 'cm-how-it-works',
  imports: [RouterLink],
  templateUrl: './how-it-works.html',
  styleUrl: './how-it-works.scss',
})
export class HowItWorks implements OnInit {
  private readonly seo = inject(SeoService);

  ngOnInit(): void {
    this.seo.apply({
      title: 'Comment ça marche',
      description:
        'Louer un mobil-home ou un bungalow au CHM Montalivet ou à Euronat avec Cap Monta : ' +
        'calendrier de disponibilités à jour, demande envoyée directement au propriétaire, sans commission.',
      path: '/comment-ca-marche',
    });
  }
}
