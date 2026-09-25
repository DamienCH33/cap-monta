import { DOCUMENT } from '@angular/common';
import { inject, Injectable } from '@angular/core';
import { Meta, Title } from '@angular/platform-browser';

import { environment } from '../../../environments/environment';
import { currentLangOption, LANGS, pathIn } from '../i18n/lang';
import { SITE_PHOTOS } from '../site-photos';

/** Aperçu des liens partagés par défaut : la photo d'accueil recadrée par `npm run photos`. */
const DEFAULT_IMAGE = SITE_PHOTOS.hero ? '/photos/site/partage.jpg' : null;

export interface SeoTags {
  /** Titre de l'onglet et des partages. « | Cap Monta » est ajouté automatiquement. */
  title: string;
  description: string;
  /** Chemin canonique, sans paramètres de requête. Commence par « / ». */
  path: string;
  /** Chemin d'une image du site (« /photos/site/partage.jpg ») ou adresse complète d'une photo de logement. */
  image?: string;
  /** Vrai pour une page qui ne doit pas être indexée : erreur, résultat vide, espace privé. */
  noindex?: boolean;
  /**
   * Page qui n'existe qu'en français (pages légales, propriétaires) : l'adresse canonique est
   * la française dans toutes les langues, sans variantes hreflang.
   */
  frenchOnly?: boolean;
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
    const lang = currentLangOption();
    const url = `${environment.siteUrl}${tags.frenchOnly ? '' : lang.prefix}${tags.path}`;
    // Une photo de logement a déjà son adresse complète (stockage des photos) ; les images du site, non.
    const path = tags.image ?? DEFAULT_IMAGE;
    const image =
      null === path ? null : /^https?:\/\//.test(path) ? path : `${environment.siteUrl}${path}`;
    const title = `${tags.title} | Cap Monta`;

    this.titleService.setTitle(title);

    this.meta.updateTag({ name: 'description', content: tags.description });

    this.meta.updateTag({
      name: 'robots',
      content: true === tags.noindex ? 'noindex, follow' : 'index, follow',
    });

    this.meta.updateTag({ property: 'og:site_name', content: 'Cap Monta' });
    this.meta.updateTag({ property: 'og:locale', content: lang.tag.replace('-', '_') });
    this.meta.updateTag({ property: 'og:type', content: 'website' });
    this.meta.updateTag({ property: 'og:title', content: title });
    this.meta.updateTag({ property: 'og:description', content: tags.description });
    this.meta.updateTag({ property: 'og:url', content: url });
    if (null === image) {
      this.meta.removeTag('property="og:image"');
    } else {
      this.meta.updateTag({ property: 'og:image', content: image });
    }

    this.meta.updateTag({ name: 'twitter:card', content: 'summary_large_image' });

    this.setCanonical(url);
    this.setAlternates(true === tags.frenchOnly || true === tags.noindex ? null : tags.path);
    this.clearJsonLd();
  }

  /**
   * Les versions de la page dans les autres langues (hreflang), pour que Google montre à
   * chacun la sienne ; x-default = le français. Aucune pour une page non traduite.
   */
  private setAlternates(path: string | null): void {
    const head = this.document.head;
    head.querySelectorAll('link[rel="alternate"][hreflang]').forEach((link) => link.remove());

    if (null === path) {
      return;
    }

    const entries: [string, string][] = [
      ...LANGS.map((lang): [string, string] => [lang.code, pathIn(lang, path)]),
      ['x-default', path],
    ];

    for (const [hreflang, href] of entries) {
      const link = this.document.createElement('link');
      link.setAttribute('rel', 'alternate');
      link.setAttribute('hreflang', hreflang);
      link.setAttribute('href', `${environment.siteUrl}${href}`);
      head.appendChild(link);
    }
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
