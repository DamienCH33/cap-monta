import { Component, computed, ElementRef, input, signal, viewChild } from '@angular/core';

import { Photo } from '../../../core/models/accommodation';
import { Icon } from '../../../shared/icon/icon';

/** Largeur de la miniature fabriquée par l'API (PhotoResizer) : 600 px, jamais agrandie. */
const THUMB_WIDTH = 600;

/** Au-delà, la mosaïque s'arrête et la dernière tuile annonce le reste (« +3 »). */
const TILES = 5;

/**
 * Photos de la fiche logement.
 *
 * Grand écran : une mosaïque (la couverture en grand, jusqu'à quatre à côté). Mobile : une
 * bande qu'on fait défiler au doigt. Un clic ouvre la visionneuse plein écran, un <dialog>
 * natif : Échap, le focus et l'arrière-plan inerte sont gérés par le navigateur.
 */
@Component({
  selector: 'cm-photo-gallery',
  imports: [Icon],
  templateUrl: './photo-gallery.html',
  styleUrl: './photo-gallery.scss',
})
export class PhotoGallery {
  readonly photos = input.required<Photo[]>();
  /** Décrit le logement, pour le texte alternatif : « Mobil-home Europa ». */
  readonly label = input.required<string>();

  readonly current = signal(0);
  readonly hidden = computed(() => Math.max(0, this.photos().length - TILES));

  private readonly dialog = viewChild<ElementRef<HTMLDialogElement>>('viewer');
  private touchStartX: number | null = null;

  alt(index: number): string {
    return `${this.label()}, photo ${index + 1} sur ${this.photos().length}`;
  }

  /** Le navigateur choisit la miniature sur mobile et la grande version ailleurs. */
  srcset(photo: Photo): string {
    return `${photo.thumbUrl} ${Math.min(THUMB_WIDTH, photo.width)}w, ${photo.url} ${photo.width}w`;
  }

  open(index: number): void {
    this.current.set(index);
    this.dialog()?.nativeElement.showModal();
  }

  close(): void {
    this.dialog()?.nativeElement.close();
  }

  previous(): void {
    const count = this.photos().length;
    this.current.update((index) => (index - 1 + count) % count);
  }

  next(): void {
    this.current.update((index) => (index + 1) % this.photos().length);
  }

  onKeydown(event: KeyboardEvent): void {
    if ('ArrowLeft' === event.key) {
      this.previous();
    } else if ('ArrowRight' === event.key) {
      this.next();
    }
  }

  /** Un clic sur le fond sombre, hors de la photo et des boutons, ferme la visionneuse. */
  onBackdropClick(event: MouseEvent): void {
    if (event.target === event.currentTarget) {
      this.close();
    }
  }

  onTouchStart(event: TouchEvent): void {
    this.touchStartX = event.changedTouches[0]?.clientX ?? null;
  }

  onTouchEnd(event: TouchEvent): void {
    const end = event.changedTouches[0]?.clientX;

    if (null === this.touchStartX || undefined === end) {
      return;
    }

    const distance = end - this.touchStartX;
    this.touchStartX = null;

    // 50 px : en dessous, c'est un tapotement ou un doigt qui tremble, pas un glissement.
    if (distance > 50) {
      this.previous();
    } else if (distance < -50) {
      this.next();
    }
  }
}
