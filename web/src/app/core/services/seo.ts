import { DOCUMENT } from '@angular/common';
import { inject, Injectable } from '@angular/core';
import { Meta, Title } from '@angular/platform-browser';

import { environment } from '../../../environments/environment';

export interface SeoTags {
  /** Titre de l'onglet et des partages. « | Cap Monta » est ajouté automatiquement. */
  title: string;
  description: string;
  /** Chemin canonique, sans paramètres de requête. Commence par « / ». */
  path: string;
  /** Chemin d'une image du site (« /hero-desktop.png ») ou adresse complète d'une photo de logement. */
  image?: string;
  /** Vrai pour une page qui ne doit pas être indexée : erreur, résultat vide, espace privé. */
  noindex?: boolean;
}

@Injectable({ providedIn: 'root' })
export class SeoService {
  private readonly titleService = inject(Title);
  private readonly meta = inject(Meta);
  private readonly document = inject(DOCUMENT);

  private static readonly JSON_LD_ID = 'cm-json-ld';

  /**
   * Pose toutes les métadonnées d'une page. Efface le JSON-LD précédent :
   * une page qui n'en déclare pas ne doit pas hériter de celui d'avant.
   */
  apply(tags: SeoTags): void {
    const url = `${environment.siteUrl}${tags.path}`;
    // Une photo de logement a déjà son adresse complète (stockage des photos) ; les images du site, non.
    const image = /^https?:\/\//.test(tags.image ?? '')
      ? (tags.image as string)
      : `${environment.siteUrl}${tags.image ?? '/hero-desktop.png'}`;
    const title = `${tags.title} | Cap Monta`;

    this.titleService.setTitle(title);

    this.meta.updateTag({ name: 'description', content: tags.description });

    this.meta.updateTag({
      name: 'robots',
      content: true === tags.noindex ? 'noindex, follow' : 'index, follow',
    });

    this.meta.updateTag({ property: 'og:site_name', content: 'Cap Monta' });
    this.meta.updateTag({ property: 'og:locale', content: 'fr_FR' });
    this.meta.updateTag({ property: 'og:type', content: 'website' });
    this.meta.updateTag({ property: 'og:title', content: title });
    this.meta.updateTag({ property: 'og:description', content: tags.description });
    this.meta.updateTag({ property: 'og:url', content: url });
    this.meta.updateTag({ property: 'og:image', content: image });

    this.meta.updateTag({ name: 'twitter:card', content: 'summary_large_image' });

    this.setCanonical(url);
    this.clearJsonLd();
  }

  private setCanonical(url: string): void {
    this.document.head
      .querySelector<HTMLLinkElement>('link[rel="canonical"]')
      ?.setAttribute('href', url);
  }

  /** Données structurées de la page, à appeler après apply(). */
  setJsonLd(data: unknown): void {
    const script = this.document.head.querySelector(`#${SeoService.JSON_LD_ID}`);

    if (null !== script) {
      script.textContent = JSON.stringify(data);
    }
  }

  private clearJsonLd(): void {
    const script = this.document.head.querySelector(`#${SeoService.JSON_LD_ID}`);

    if (null !== script) {
      script.textContent = '';
    }
  }
}
