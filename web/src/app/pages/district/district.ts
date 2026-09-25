import { Component, computed, inject, OnInit, signal } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { catchError, combineLatest, map, of, switchMap } from 'rxjs';

import { environment } from '../../../environments/environment';
import { Accommodation } from '../../core/models/accommodation';
import { District, DISTRICT_AREAS } from '../../core/models/district';
import { AccommodationService } from '../../core/services/accommodation';
import { DistrictService } from '../../core/services/district';
import { SeoService } from '../../core/services/seo';
import { AccommodationCard } from '../../shared/accommodation-card/accommodation-card';
import { Pagination } from '../../shared/pagination/pagination';
import { HttpStatus } from '../../core/services/http-status';
import { SITE_PHOTOS } from '../../core/site-photos';
import { currentLang, currentLangOption } from '../../core/i18n/lang';
import { euros } from '../../core/models/stay-terms';
import { SitePhoto } from '../../shared/site-photo/site-photo';

@Component({
  selector: 'cm-district',
  imports: [AccommodationCard, Pagination, RouterLink, SitePhoto],
  templateUrl: './district.html',
  styleUrl: './district.scss',
})
export class DistrictPage implements OnInit {
  readonly euros = euros;

  private readonly route = inject(ActivatedRoute);
  private readonly districtApi = inject(DistrictService);
  private readonly accommodations = inject(AccommodationService);
  private readonly seo = inject(SeoService);

  readonly district = signal<District | null>(null);
  readonly missing = signal(false);
  private readonly httpStatus = inject(HttpStatus);
  readonly results = signal<Accommodation[]>([]);
  readonly total = signal(0);
  readonly page = signal(1);
  readonly totalPages = signal(1);

  private readonly allDistricts = signal<District[]>([]);

  readonly area = computed(
    () => DISTRICT_AREAS.find((area) => area.key === this.district()?.area) ?? null,
  );

  /** La photo de la zone (dunes, cœur du domaine, avenue), si elle a été ajoutée. */
  readonly areaPhoto = computed(() => {
    const zone = this.area();

    return zone && SITE_PHOTOS[zone.key] ? zone.key : null;
  });

  /** Des quartiers comparables, pas mitoyens : le plan ne nous donne pas l'adjacence réelle. */
  readonly neighbours = computed(() => {
    const current = this.district();

    if (null === current) {
      return [];
    }

    const others = this.allDistricts().filter(
      (district) =>
        district.slug !== current.slug &&
        district.resort === current.resort &&
        district.accommodationCount > 0,
    );

    // Sans zone établie (Pins, Californie), on propose simplement d'autres quartiers du domaine.
    const sameArea = null === current.area ? [] : others.filter((d) => d.area === current.area);

    return (sameArea.length > 0 ? sameArea : others).slice(0, 6);
  });

  readonly neighboursTitle = computed(() => {
    const zone = this.area();

    if (null === zone) {
      return $localize`:@@district.others:Autres quartiers du domaine`;
    }
    const label = 'de' === currentLang() ? zone.label : zone.label.toLowerCase();

    return $localize`:@@district.others-in:Autres quartiers ${label}:zone:`;
  });

  ngOnInit(): void {
    this.districtApi.list().subscribe((districts) => this.allDistricts.set(districts));

    // Le slug vient de l'adresse, la page des paramètres : les deux doivent déclencher un rechargement.
    combineLatest([this.route.paramMap, this.route.queryParamMap])
      .pipe(
        map(([params, query]) => ({
          slug: params.get('slug') ?? '',
          page: Number(query.get('page')) || 1,
        })),
        switchMap(({ slug, page }) =>
          this.districtApi.get(slug).pipe(
            switchMap((district) =>
              this.accommodations
                .searchPage({ districts: [district.slug], page })
                .pipe(map((found) => ({ district, found }))),
            ),
            // Slug inconnu ou API injoignable : on affiche une page « introuvable », pas une page vide.
            catchError(() => of(null)),
          ),
        ),
      )
      .subscribe((result) => {
        this.missing.set(null === result);
        if (null === result) {
          this.httpStatus.set(404);
        }

        if (null === result) {
          this.district.set(null);
          this.results.set([]);

          return;
        }

        this.district.set(result.district);
        this.results.set(result.found.items);
        this.total.set(result.found.total);
        this.page.set(result.found.page);
        this.totalPages.set(result.found.totalPages);
        this.applySeo(result.district, result.found.total);
      });
  }

  private applySeo(district: District, total: number): void {
    const area = DISTRICT_AREAS.find((item) => item.key === district.area);
    const resort = 'euronat' === district.resort ? 'Euronat' : 'CHM Montalivet';
    const count =
      1 === total
        ? $localize`:@@count.listing.one:1 logement`
        : $localize`:@@count.listing.other:${total}:count: logements`;

    this.seo.apply({
      title: $localize`:@@seo.district.title:Location dans le quartier ${district.name}:name: — ${resort}:resort:`,
      description: $localize`:@@seo.district.description:${count}:count: à louer dans le quartier ${district.name}:name:, au ${resort}:resort:${area ? `, ${area.label.toLowerCase()}` : ''}:area:. Calendriers tenus à jour par les propriétaires, réponse sous 48 h.`,
      path: `/quartier/${district.slug}`,
    });

    const prefix = currentLangOption().prefix;
    this.seo.setJsonLd({
      '@context': 'https://schema.org',
      '@type': 'BreadcrumbList',
      itemListElement: [
        {
          '@type': 'ListItem',
          position: 1,
          name: $localize`:@@crumbs.home:Accueil`,
          item: `${environment.siteUrl}${prefix}/`,
        },
        {
          '@type': 'ListItem',
          position: 2,
          name: $localize`:@@header.search:Rechercher`,
          item: `${environment.siteUrl}${prefix}/recherche`,
        },
        {
          '@type': 'ListItem',
          position: 3,
          name: district.name,
          item: `${environment.siteUrl}${prefix}/quartier/${district.slug}`,
        },
      ],
    });
  }
}
