import { isPlatformBrowser } from '@angular/common';
import { DestroyRef, inject, Injectable, PLATFORM_ID } from '@angular/core';
import { NavigationEnd, Router } from '@angular/router';
import { filter } from 'rxjs';

type UmamiPayload = Record<string, unknown>;

interface Umami {
  track(event?: string | ((props: UmamiPayload) => UmamiPayload), data?: UmamiPayload): void;
}

declare global {
  interface Window {
    umami?: Umami;
  }
}

/**
 * Mesure d'audience Umami : sans cookie, donc sans bandeau de consentement.
 *
 * Le script (index.html) ne compte rien tout seul (data-auto-track="false") : c'est ce
 * service qui envoie chaque page vue, avec une adresse nettoyée. Le lien personnel de suivi
 * d'une demande (/demande/<jeton>) et les paramètres (?token=…, dates, voyageurs) ne
 * sortent jamais du site. Ne compte qu'en ligne : data-domains écarte localhost.
 */
@Injectable({ providedIn: 'root' })
export class Analytics {
  private readonly router = inject(Router);
  private readonly destroyRef = inject(DestroyRef);
  private readonly isBrowser = isPlatformBrowser(inject(PLATFORM_ID));

  /** Branché une fois, au démarrage de l'application. */
  start(): void {
    if (!this.isBrowser) {
      return;
    }

    const subscription = this.router.events
      .pipe(filter((event): event is NavigationEnd => event instanceof NavigationEnd))
      .subscribe((event) => this.page(Analytics.cleanPath(event.urlAfterRedirects)));
    this.destroyRef.onDestroy(() => subscription.unsubscribe());
  }

  /** Un événement nommé (« demande-envoyee »…), sans aucune donnée personnelle. */
  event(name: string, data?: Record<string, string | number | boolean>): void {
    if (this.isBrowser) {
      this.whenReady((umami) => umami.track(name, data));
    }
  }

  /** « /en/demande/3fa9…?x=1#y » → « /en/demande/:lien ». */
  static cleanPath(url: string): string {
    const path = url.split(/[?#]/)[0] || '/';

    return path.replace(/\/demande\/[^/]+/, '/demande/:lien');
  }

  private page(path: string): void {
    this.whenReady((umami) =>
      umami.track((props) => ({
        ...props,
        url: path,
        referrer: Analytics.referrer(props['referrer']),
      })),
    );
  }

  /** Le référent garde seulement le site d'origine : jamais un lien personnel d'un autre site. */
  private static referrer(value: unknown): string {
    if ('string' !== typeof value || '' === value) {
      return '';
    }

    try {
      return new URL(value).origin;
    } catch {
      return '';
    }
  }

  /** Le script est chargé en « defer » : la première page peut arriver avant lui. */
  private whenReady(send: (umami: Umami) => void, attempt = 0): void {
    const umami = window.umami;

    if (umami) {
      send(umami);
    } else if (attempt < 20) {
      setTimeout(() => this.whenReady(send, attempt + 1), 250);
    }
  }
}
