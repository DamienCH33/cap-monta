import { Component, computed, ElementRef, inject, input, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { NavigationEnd, Router } from '@angular/router';
import { filter, map } from 'rxjs';

import { currentLang, LANGS, pathIn } from '../../core/i18n/lang';
import { Icon } from '../icon/icon';

/**
 * Changer de langue en gardant la page : « /recherche?arrivee=… » devient
 * « /en/recherche?arrivee=… ». Chaque langue est un site à part (un build par langue) :
 * de vrais liens, pas une navigation du routeur.
 */
@Component({
  selector: 'cm-lang-switch',
  imports: [Icon],
  host: {
    '(document:click)': 'onDocumentClick($event)',
    '(document:keydown.escape)': 'open.set(false)',
  },
  template: `
    @if ('list' === mode()) {
      <ul class="langs">
        @for (lang of langs; track lang.code) {
          <li>
            <a
              [href]="href(lang.code)"
              [attr.hreflang]="lang.code"
              [attr.lang]="lang.code"
              [attr.aria-current]="lang.code === current ? 'true' : null"
              >{{ lang.label }}</a
            >
          </li>
        }
      </ul>
    } @else {
      <button
        type="button"
        class="switch"
        [attr.aria-expanded]="open()"
        aria-label="Langue"
        i18n-aria-label="@@lang.switch"
        (click)="open.set(!open())"
      >
        <cm-icon name="world" />
        <span>{{ current.toUpperCase() }}</span>
      </button>
      @if (open()) {
        <ul class="menu">
          @for (lang of langs; track lang.code) {
            <li>
              <a
                [href]="href(lang.code)"
                [attr.hreflang]="lang.code"
                [attr.lang]="lang.code"
                [attr.aria-current]="lang.code === current ? 'true' : null"
                >{{ lang.label }}</a
              >
            </li>
          }
        </ul>
      }
    }
  `,
  styleUrl: './lang-switch.scss',
})
export class LangSwitch {
  private readonly router = inject(Router);
  private readonly host = inject<ElementRef<HTMLElement>>(ElementRef);

  /** Menu déroulant (en-tête) ou liste de liens (pied de page). */
  readonly mode = input<'menu' | 'list'>('menu');

  readonly langs = LANGS;
  readonly current = currentLang();
  readonly open = signal(false);

  /** L'adresse de la page, sans préfixe de langue (le routeur ne le voit pas : base href). */
  private readonly url = toSignal(
    this.router.events.pipe(
      filter((event) => event instanceof NavigationEnd),
      map(() => this.router.url),
    ),
    { initialValue: this.router.url },
  );
  private readonly path = computed(() => this.url() || '/');

  href(code: string): string {
    const lang = LANGS.find((item) => item.code === code) ?? LANGS[0];

    return pathIn(lang, this.path());
  }

  onDocumentClick(event: MouseEvent): void {
    if (this.open() && !this.host.nativeElement.contains(event.target as Node)) {
      this.open.set(false);
    }
  }
}
