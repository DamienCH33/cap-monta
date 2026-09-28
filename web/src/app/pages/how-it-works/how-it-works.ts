import { Component, OnInit, inject } from '@angular/core';
import { RouterLink } from '@angular/router';

import { environment } from '../../../environments/environment';
import { SeoService } from '../../core/services/seo';

@Component({
  selector: 'cm-how-it-works',
  imports: [RouterLink],
  templateUrl: './how-it-works.html',
  styleUrl: './how-it-works.scss',
})
export class HowItWorks implements OnInit {
  private readonly seo = inject(SeoService);
  /** Page de dons : le lien n'apparaît que si elle est renseignée. */
  readonly supportUrl = environment.supportUrl;

  ngOnInit(): void {
    this.seo.apply({
      title: $localize`:@@footer.how:Comment ça marche`,
      description: $localize`:@@seo.how.description:Louer un mobil-home ou un bungalow au CHM Montalivet ou à Euronat avec Cap Monta : calendrier de disponibilités à jour, demande envoyée directement au propriétaire, sans commission.`,
      path: '/comment-ca-marche',
    });
  }
}
