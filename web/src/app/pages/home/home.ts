import { Component, inject, OnInit, signal } from '@angular/core';
import { Params, RouterLink } from '@angular/router';

import { Accommodation } from '../../core/models/accommodation';
import { District } from '../../core/models/district';
import { AccommodationService } from '../../core/services/accommodation';
import { DistrictService } from '../../core/services/district';
import { AccommodationCard } from '../../shared/accommodation-card/accommodation-card';
import { SearchBar } from '../../shared/search-bar/search-bar';
import { SeoService } from '../../core/services/seo';
import { environment } from '../../../environments/environment';

@Component({
  selector: 'cm-home',
  imports: [RouterLink, SearchBar, AccommodationCard],
  templateUrl: './home.html',
  styleUrl: './home.scss',
})
export class Home implements OnInit {
  private readonly accommodations = inject(AccommodationService);
  private readonly districtApi = inject(DistrictService);
  private readonly seo = inject(SeoService);

  readonly highlights = signal<Accommodation[]>([]);
  readonly districts = signal<District[]>([]);

  ngOnInit(): void {
    this.seo.apply({
      title: 'Location de mobil-homes et bungalows au CHM Montalivet et à Euronat',
      description:
        'Louez un bungalow, un mobil-home ou une caravane au CHM Montalivet et à Euronat. ' +
        'Calendriers tenus à jour par les propriétaires, réponse sous 48 h, aucune commission.',
      path: '/',
    });

    this.seo.setJsonLd({
      '@context': 'https://schema.org',
      '@graph': [
        {
          '@type': 'WebSite',
          '@id': `${environment.siteUrl}/#website`,
          url: environment.siteUrl,
          name: 'Cap Monta',
          inLanguage: 'fr-FR',
          publisher: { '@id': `${environment.siteUrl}/#organization` },
        },
        {
          '@type': 'Organization',
          '@id': `${environment.siteUrl}/#organization`,
          name: 'Cap Monta',
          url: environment.siteUrl,
          description:
            'Mise en relation entre propriétaires et locataires de bungalows, mobil-homes et ' +
            'caravanes au CHM Montalivet et à Euronat. Sans commission.',
          areaServed: [
            { '@type': 'Place', name: 'CHM Montalivet' },
            { '@type': 'Place', name: 'Euronat' },
          ],
        },
      ],
    });

    this.accommodations.search({}).subscribe((found) => this.highlights.set(found.slice(0, 3)));
    this.districtApi.list().subscribe((found) => this.districts.set(found));
  }

  queryParamsFor(district: District): Params {
    return { quartier: district.name };
  }
}
