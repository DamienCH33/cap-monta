import { Component, computed, inject, OnInit, signal } from '@angular/core';
import { RouterLink } from '@angular/router';

import { Accommodation } from '../../core/models/accommodation';
import { District, DistrictArea } from '../../core/models/district';
import { AccommodationService } from '../../core/services/accommodation';
import { DistrictService } from '../../core/services/district';
import { AccommodationCard } from '../../shared/accommodation-card/accommodation-card';
import { SearchBar } from '../../shared/search-bar/search-bar';
import { Icon } from '../../shared/icon/icon';
import { SitePhoto } from '../../shared/site-photo/site-photo';
import { SITE_PHOTOS } from '../../core/site-photos';
import { SeoService } from '../../core/services/seo';
import { environment } from '../../../environments/environment';

const ZONES: readonly { key: DistrictArea; title: string; hint: string }[] = [
  { key: 'dunes', title: 'Côté océan', hint: 'Dunes et plage à pied' },
  { key: 'central', title: 'Au cœur du domaine', hint: 'Commerces, piscines, thermes' },
  { key: 'roadside', title: 'Côté avenue', hint: 'Au calme, sous les pins' },
];

@Component({
  selector: 'cm-home',
  imports: [RouterLink, SearchBar, AccommodationCard, Icon, SitePhoto],
  templateUrl: './home.html',
  styleUrl: './home.scss',
})
export class Home implements OnInit {
  private readonly accommodations = inject(AccommodationService);
  private readonly districtApi = inject(DistrictService);
  private readonly seo = inject(SeoService);

  readonly heroPhoto = SITE_PHOTOS.hero ?? null;
  readonly ownerPhoto = SITE_PHOTOS.owner ?? null;

  readonly highlights = signal<Accommodation[]>([]);
  readonly districts = signal<District[]>([]);

  /** Les quartiers regroupés par zone, de la plage vers l'avenue. */
  readonly zones = computed(() =>
    ZONES.map((zone) => {
      const districts = this.districts().filter((district) => district.area === zone.key);

      return {
        ...zone,
        districts,
        count: districts.reduce((sum, district) => sum + district.accommodationCount, 0),
      };
    }).filter((zone) => zone.districts.length > 0),
  );

  /** Les quartiers dont la zone n'est pas encore relevée sur le plan. */
  readonly unplaced = computed(() => this.districts().filter((district) => null === district.area));

  ngOnInit(): void {
    this.seo.apply({
      title: 'Location de mobil-homes et bungalows au CHM Montalivet et à Euronat',
      description:
        'Louez un bungalow, un mobil-home, une caravane, un chalet ou un studio au CHM Montalivet et à Euronat. ' +
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
            'Mise en relation entre propriétaires et locataires de bungalows, mobil-homes, ' +
            'caravanes, chalets et studios au CHM Montalivet et à Euronat. Sans commission.',
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
}
