import { isPlatformBrowser } from '@angular/common';
import { Component, computed, ElementRef, inject, input, PLATFORM_ID, signal } from '@angular/core';

import { Icon } from '../icon/icon';

/**
 * « Partager » une annonce : la feuille de partage du téléphone quand elle existe ; sinon un
 * petit menu (copier le lien, WhatsApp, email). L'aperçu du lien (photo, titre) vient des
 * balises Open Graph de la fiche.
 */
@Component({
  selector: 'cm-share-button',
  imports: [Icon],
  host: {
    '(document:click)': 'onDocumentClick($event)',
    '(document:keydown.escape)': 'open.set(false)',
  },
  template: `
    <button type="button" class="share" [attr.aria-expanded]="open()" (click)="share()">
      <cm-icon name="share" />
      <span>
        @if (copied()) {
          <ng-container i18n="@@share.copied">Lien copié</ng-container>
        } @else {
          <ng-container i18n="@@share.share">Partager</ng-container>
        }
      </span>
    </button>
    @if (open()) {
      <div class="share__menu" role="menu">
        <button type="button" role="menuitem" (click)="copy()" i18n="@@share.copy">Copier le lien</button>
        <a role="menuitem" [href]="whatsapp()" target="_blank" rel="noopener">WhatsApp</a>
        <a role="menuitem" [href]="mail()" i18n="@@share.email">Email</a>
      </div>
    }
  `,
  styleUrl: './share-button.scss',
})
export class ShareButton {
  private readonly isBrowser = isPlatformBrowser(inject(PLATFORM_ID));
  private readonly host = inject<ElementRef<HTMLElement>>(ElementRef);

  /** Chemin de la page (« /logement/… »), sans les dates de la recherche. */
  readonly path = input.required<string>();
  readonly title = input.required<string>();

  readonly open = signal(false);
  readonly copied = signal(false);

  private readonly url = computed(() =>
    this.isBrowser ? `${location.origin}${this.path()}` : this.path(),
  );
  readonly whatsapp = computed(
    () => `https://wa.me/?text=${encodeURIComponent(`${this.title()} ${this.url()}`)}`,
  );
  readonly mail = computed(
    () =>
      `mailto:?subject=${encodeURIComponent(this.title())}&body=${encodeURIComponent(this.url())}`,
  );

  async share(): Promise<void> {
    if (!this.isBrowser) {
      return;
    }
    if (navigator.share) {
      try {
        await navigator.share({ title: this.title(), url: this.url() });
      } catch {
        // Partage annulé : rien à faire.
      }

      return;
    }
    this.open.update((value) => !value);
  }

  async copy(): Promise<void> {
    try {
      await navigator.clipboard.writeText(this.url());
      this.copied.set(true);
      setTimeout(() => this.copied.set(false), 3000);
    } catch {
      // Presse-papiers refusé : le lien reste dans la barre d'adresse.
    }
    this.open.set(false);
  }

  onDocumentClick(event: MouseEvent): void {
    if (this.open() && !this.host.nativeElement.contains(event.target as Node)) {
      this.open.set(false);
    }
  }
}
