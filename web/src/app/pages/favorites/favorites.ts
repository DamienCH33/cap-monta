import { isPlatformBrowser } from '@angular/common';
import { Component, computed, inject, OnInit, PLATFORM_ID, signal } from '@angular/core';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { catchError, forkJoin, map, of } from 'rxjs';

import { Accommodation } from '../../core/models/accommodation';
import { AccommodationService } from '../../core/services/accommodation';
import { Favorites } from '../../core/services/favorites';
import { SeoService } from '../../core/services/seo';
import { AccommodationCard } from '../../shared/accommodation-card/accommodation-card';
import { Icon } from '../../shared/icon/icon';

/**
 * « Mes favoris » : les logements mis de côté sur cet appareil. Un lien de partage contient la
 * liste (?liste=slug1,slug2), pour l'envoyer à sa famille ou la rouvrir sur un autre appareil.
 */
@Component({
  selector: 'cm-favorites',
  imports: [AccommodationCard, Icon, RouterLink],
  templateUrl: './favorites.html',
  styleUrl: './favorites.scss',
})
export class FavoritesPage implements OnInit {
  private readonly accommodations = inject(AccommodationService);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly isBrowser = isPlatformBrowser(inject(PLATFORM_ID));
  readonly favorites = inject(Favorites);

  /** Liste reçue par un lien partagé ; null quand on regarde ses propres favoris. */
  readonly shared = signal<string[] | null>(null);
  readonly items = signal<Accommodation[]>([]);
  readonly missing = signal(0);
  readonly loading = signal(true);
  readonly copied = signal(false);

  readonly slugs = computed(() => this.shared() ?? this.favorites.list());

  constructor() {
    inject(SeoService).apply({
      title: 'Mes favoris',
      description: 'Les logements que vous avez mis de côté.',
      path: '/favoris',
      noindex: true,
    });
  }

  ngOnInit(): void {
    const list = this.route.snapshot.queryParamMap.get('liste');
    if (list) {
      this.shared.set(list.split(',').filter(Boolean).slice(0, 50));
    }
    this.load();
  }

  load(): void {
    const slugs = this.slugs();
    if (0 === slugs.length) {
      this.items.set([]);
      this.loading.set(false);

      return;
    }

    forkJoin(
      slugs.map((slug) =>
        this.accommodations.getBySlug(slug).pipe(catchError(() => of(null))),
      ),
    )
      .pipe(map((found) => found.filter((item): item is Accommodation => null !== item)))
      .subscribe((found) => {
        this.items.set(found);
        this.missing.set(slugs.length - found.length);
        this.loading.set(false);
      });
  }

  /** Retire de ses favoris et de la liste affichée, sans recharger. */
  remove(slug: string): void {
    this.favorites.remove(slug);
    this.items.update((items) => items.filter((item) => item.slug !== slug));
  }

  /** Garde la sélection reçue, puis revient à ses propres favoris. */
  keepShared(): void {
    this.favorites.addAll(this.shared() ?? []);
    this.shared.set(null);
    this.router.navigate(['/favoris']);
  }

  async share(): Promise<void> {
    if (!this.isBrowser) {
      return;
    }
    const url = `${location.origin}/favoris?liste=${this.favorites.list().join(',')}`;
    try {
      if (navigator.share) {
        await navigator.share({ title: 'Ma sélection Cap Monta', url });

        return;
      }
      await navigator.clipboard.writeText(url);
      this.copied.set(true);
      setTimeout(() => this.copied.set(false), 3000);
    } catch {
      // Partage annulé ou presse-papiers refusé : rien à signaler.
    }
  }
}
