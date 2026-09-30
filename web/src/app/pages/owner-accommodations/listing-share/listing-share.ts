import { isPlatformBrowser } from '@angular/common';
import {
  ChangeDetectionStrategy,
  Component,
  computed,
  ElementRef,
  inject,
  input,
  PLATFORM_ID,
  signal,
  viewChild,
} from '@angular/core';

import { environment } from '../../../../environments/environment';
import { Icon } from '../../../shared/icon/icon';

/** Le QR code d'une vitre : lisible à un mètre, donc de gros modules. */
const PNG_CELL = 24;
const PNG_MARGIN = 4;

/**
 * « Partager » une annonce en ligne, depuis Mes logements : le lien à coller dans un post
 * Facebook, et un QR code à imprimer pour la vitre du mobil-home. Le QR code porte
 * `?utm_source=qrcode` : Umami compte à part les visites venues d'une vitre.
 */
@Component({
  selector: 'cm-listing-share',
  imports: [Icon],
  templateUrl: './listing-share.html',
  styleUrl: './listing-share.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ListingShare {
  private readonly isBrowser = isPlatformBrowser(inject(PLATFORM_ID));

  readonly slug = input.required<string>();
  /** « Mobil-home · Les Pins » : repris sous le QR code imprimé. */
  readonly label = input.required<string>();

  private readonly dialog = viewChild.required<ElementRef<HTMLDialogElement>>('dialog');

  readonly url = computed(() => `${environment.siteUrl}/logement/${this.slug()}`);
  private readonly qrUrl = computed(() => `${this.url()}?utm_source=qrcode`);

  /** Image du QR code (data:image/gif), sans innerHTML : Angular la contrôle comme toute URL. */
  readonly qrImage = signal<string | null>(null);
  readonly copied = signal(false);
  readonly copyFailed = signal(false);

  /** Chargée au premier clic : la bibliothèque ne pèse rien sur le reste du site. */
  private async qrcode() {
    const { default: qrcode } = await import('qrcode-generator');
    const qr = qrcode(0, 'M');
    qr.addData(this.qrUrl());
    qr.make();

    return qr;
  }

  async open(): Promise<void> {
    if (!this.isBrowser) {
      return;
    }
    this.copied.set(false);
    this.copyFailed.set(false);
    this.dialog().nativeElement.showModal();

    if (null === this.qrImage()) {
      this.qrImage.set((await this.qrcode()).createDataURL(8, 2));
    }
  }

  close(): void {
    this.dialog().nativeElement.close();
  }

  /** Un clic sur le fond (hors de la boîte) ferme la fenêtre. */
  onBackdrop(event: MouseEvent): void {
    if (event.target === this.dialog().nativeElement) {
      this.close();
    }
  }

  async copy(): Promise<void> {
    try {
      await navigator.clipboard.writeText(this.url());
      this.copied.set(true);
      this.copyFailed.set(false);
    } catch {
      this.copyFailed.set(true);
    }
  }

  /** L'image à imprimer : le QR code et deux lignes de texte dessous, sur fond blanc. */
  async download(): Promise<void> {
    const qr = await this.qrcode();
    const modules = qr.getModuleCount() + 2 * PNG_MARGIN;
    const size = modules * PNG_CELL;
    const textHeight = 7 * PNG_CELL;

    const canvas = document.createElement('canvas');
    canvas.width = size;
    canvas.height = size + textHeight;
    const context = canvas.getContext('2d');
    if (null === context) {
      return;
    }

    context.fillStyle = '#fff';
    context.fillRect(0, 0, canvas.width, canvas.height);
    context.fillStyle = '#000';
    for (let row = 0; row < qr.getModuleCount(); row++) {
      for (let col = 0; col < qr.getModuleCount(); col++) {
        if (qr.isDark(row, col)) {
          context.fillRect(
            (col + PNG_MARGIN) * PNG_CELL,
            (row + PNG_MARGIN) * PNG_CELL,
            PNG_CELL,
            PNG_CELL,
          );
        }
      }
    }

    context.textAlign = 'center';
    context.fillStyle = '#0f3b55';
    context.font = `700 ${Math.round(PNG_CELL * 2.2)}px Helvetica, Arial, sans-serif`;
    context.fillText(
      'Dates libres et tarifs',
      size / 2,
      size + PNG_CELL * 1.5,
      size - 2 * PNG_CELL,
    );
    context.fillStyle = '#4b5b63';
    context.font = `400 ${Math.round(PNG_CELL * 1.5)}px Helvetica, Arial, sans-serif`;
    context.fillText(
      `${this.label()} · Cap Monta`,
      size / 2,
      size + PNG_CELL * 4,
      size - 2 * PNG_CELL,
    );

    const link = document.createElement('a');
    link.href = canvas.toDataURL('image/png');
    link.download = `qr-code-${this.slug()}.png`;
    link.click();
  }
}
