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
import { currentLang, currentLangOption } from '../../core/i18n/lang';
import { SeoService } from '../../core/services/seo';
import { environment } from '../../../environments/environment';

const ZONES: readonly { key: DistrictArea; title: string; hint: string }[] = [
  {
    key: 'dunes',
    title: $localize`:@@zone.dunes:Côté océan`,
    hint: $localize`:@@zone.dunes-hint:Dunes et plage à pied`,
  },
  {
    key: 'central',
    title: $localize`:@@zone.central:Au cœur du domaine`,
    hint: $localize`:@@zone.central-hint:Commerces, piscines, thermes`,
  },
  {
    key: 'roadside',
    title: $localize`:@@zone.roadside:Côté avenue`,
    hint: $localize`:@@zone.roadside-hint:Au calme, sous les pins`,
  },
];

/**
 * Les secteurs d'Euronat regroupés comme le fait le domaine dans sa grille tarifaire 2026 :
 * « côté plage & Afrique » (Afrique II avec Afrique), « côté village Europe, Asie, Océanie »,
 * « côté camping Polynésie, Mélèzes » (Polynésie et les parcs des mobil-homes). Un secteur ajouté
 * plus tard et rangé nulle part s'affiche sous les cartes. Par slug, pour ne pas dépendre des textes.
 */
const EURONAT_ZONES: readonly { key: string; title: string; hint: string; slugs: string[] }[] = [
  {
    key: 'euronat-beach',
    title: $localize`:@@zone.euronat-beach:Côté plage`,
    hint: $localize`:@@zone.euronat-beach-hint:Plage Nord ou Sud à pied`,
    slugs: [
      'euronat-amerique-du-nord',
      'euronat-amerique-du-sud',
      'euronat-afrique',
      'euronat-afrique-ii',
    ],
  },
  {
    key: 'euronat-village',
    title: $localize`:@@zone.euronat-village:Côté village`,
    hint: $localize`:@@zone.euronat-village-hint:Près des commerces`,
    slugs: ['euronat-europe', 'euronat-asie', 'euronat-oceanie'],
  },
  {
    key: 'euronat-camping',
    title: $localize`:@@zone.euronat-camping:Côté camping`,
    hint: $localize`:@@zone.euronat-camping-hint:Près de l'accueil`,
    slugs: [
      'euronat-polynesie',
      'euronat-parc-des-lauriers',
      'euronat-parc-des-melezes',
      'euronat-parc-des-oyats',
      'euronat-parc-des-acacias',
      'euronat-parc-des-mimosas',
      'euronat-parc-des-chataigniers',
      'euronat-parc-des-albizzias',
    ],
  },
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
  readonly french = 'fr' === currentLang();
  readonly ownerPhoto = SITE_PHOTOS.owner ?? null;

  readonly highlights = signal<Accommodation[]>([]);
  readonly districts = signal<District[]>([]);

  /** Les zones (dunes, cœur, avenue) sont celles du plan du CHM : Euronat a ses secteurs à part. */
  private readonly chmDistricts = computed(() =>
    this.districts().filter((district) => 'chm' === district.resort),
  );
  readonly euronatDistricts = computed(() =>
    this.districts().filter((district) => 'euronat' === district.resort),
  );

  /** Les quartiers du CHM regroupés par zone, de la plage vers l'avenue. */
  readonly zones = computed(() =>
    ZONES.map((zone) => {
      const districts = this.chmDistricts().filter((district) => district.area === zone.key);

      return {
        ...zone,
        districts,
        count: districts.reduce((sum, district) => sum + district.accommodationCount, 0),
      };
    }).filter((zone) => zone.districts.length > 0),
  );

  /** Les secteurs d'Euronat en trois cartes, dans l'ordre du plan. */
  readonly euronatZones = computed(() =>
    EURONAT_ZONES.map((zone) => {
      const districts = this.euronatDistricts().filter((district) =>
        zone.slugs.includes(district.slug),
      );

      return {
        ...zone,
        districts,
        count: districts.reduce((sum, district) => sum + district.accommodationCount, 0),
      };
    }).filter((zone) => zone.districts.length > 0),
  );

  /** Les secteurs d'Euronat rangés dans aucune carte (Afrique II, Polynésie, un ajout futur). */
  readonly euronatOthers = computed(() => {
    const placed = new Set(EURONAT_ZONES.flatMap((zone) => zone.slugs));

    return this.euronatDistricts().filter((district) => !placed.has(district.slug));
  });

  /** Les quartiers dont la zone n'est pas encore relevée sur le plan. */
  readonly unplaced = computed(() =>
    this.chmDistricts().filter((district) => null === district.area),
  );

  ngOnInit(): void {
    this.seo.apply({
      title: $localize`:@@seo.home.title:Location de mobil-homes et bungalows au CHM Montalivet et à Euronat`,
      description: $localize`:@@seo.home.description:Louez un bungalow, un mobil-home, une caravane, un chalet ou un studio au CHM Montalivet et à Euronat. Calendriers tenus à jour par les propriétaires, réponse sous 48 h, aucune commission.`,
      path: '/',
    });

    this.seo.setJsonLd({
      '@context': 'https://schema.org',
      '@graph': [
        {
          '@type': 'WebSite',
          '@id': `${environment.siteUrl}/#website`,
          url: environment.siteUrl,
          // Nom affiché par Google au-dessus du résultat (sinon celui de l'hébergeur).
          name: 'Cap Monta',
          alternateName: ['CapMonta', 'Cap Monta CHM Euronat'],
          inLanguage: currentLangOption().tag,
          publisher: { '@id': `${environment.siteUrl}/#organization` },
        },
        {
          '@type': 'Organization',
          '@id': `${environment.siteUrl}/#organization`,
          name: 'Cap Monta',
          url: environment.siteUrl,
          description: $localize`:@@seo.org.description:Mise en relation entre propriétaires et locataires de bungalows, mobil-homes, caravanes, chalets et studios au CHM Montalivet et à Euronat. Sans commission.`,
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
