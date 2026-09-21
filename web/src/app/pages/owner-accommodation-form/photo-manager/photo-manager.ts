import { HttpErrorResponse, HttpEventType } from '@angular/common/http';
import {
  ChangeDetectionStrategy,
  Component,
  computed,
  DestroyRef,
  inject,
  input,
  linkedSignal,
  output,
  signal,
} from '@angular/core';
import { Observable } from 'rxjs';

import { apiErrorMessage } from '../../../core/http/api-error';
import { OwnerPhoto } from '../../../core/models/owner-accommodation';
import { OwnerAccommodationService } from '../../../core/services/owner-accommodation';
import { Icon } from '../../../shared/icon/icon';

/** Mêmes règles que l'API : on prévient avant l'envoi plutôt qu'après. */
export const MAX_PHOTOS = 12;
const MAX_BYTES = 10 * 1024 * 1024;
const ACCEPTED = ['image/jpeg', 'image/png', 'image/webp'];

/** En dessous, l'annonce reste publiable, mais on encourage à en ajouter. */
const RECOMMENDED = 5;

interface Upload {
  key: number;
  file: File;
  /** Aperçu local, affiché tout de suite, avant même la fin de l'envoi. */
  preview: string;
  progress: number;
  sending: boolean;
  error: string | null;
  /** Faux pour un fichier refusé d'avance (format, taille) : réessayer ne servirait à rien. */
  retryable: boolean;
}

/**
 * Les photos d'une annonce : envoi par glisser-déposer ou sélection, progression,
 * couverture, ordre, suppression. Chaque action part tout de suite vers l'API :
 * il n'y a rien à « enregistrer » pour les photos.
 */
@Component({
  selector: 'cm-photo-manager',
  imports: [Icon],
  templateUrl: './photo-manager.html',
  styleUrl: './photo-manager.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PhotoManager {
  private readonly service = inject(OwnerAccommodationService);

  /** Photos déjà enregistrées, fournies par le formulaire au chargement. */
  readonly initial = input<OwnerPhoto[]>([]);

  /**
   * Fournie par le formulaire : rend le slug du logement, en l'enregistrant d'abord en
   * brouillon s'il est nouveau. Une photo a besoin d'un logement existant.
   */
  readonly ensureSaved = input.required<() => Observable<string>>();

  /** Prévient le formulaire : il vérifie qu'il y a au moins une photo avant de publier. */
  readonly photosChange = output<OwnerPhoto[]>();

  readonly photos = linkedSignal(() => this.initial());
  readonly queue = signal<Upload[]>([]);

  readonly noPeople = signal(false);
  readonly dragging = signal(false);
  readonly error = signal<string | null>(null);

  /** La photo dont on demande confirmation avant de la supprimer. */
  readonly confirming = signal<string | null>(null);
  readonly busy = signal(false);

  readonly max = MAX_PHOTOS;
  readonly recommended = RECOMMENDED;

  readonly total = computed(() => this.photos().length + this.queue().length);
  readonly full = computed(() => this.total() >= MAX_PHOTOS);
  readonly canAdd = computed(() => this.noPeople() && !this.full());

  private nextKey = 0;
  private sending = false;

  constructor() {
    // Les aperçus locaux occupent de la mémoire tant qu'on ne les libère pas.
    inject(DestroyRef).onDestroy(() => this.queue().forEach((upload) => URL.revokeObjectURL(upload.preview)));
  }

  onConsent(event: Event): void {
    this.noPeople.set((event.target as HTMLInputElement).checked);
    this.error.set(null);
  }

  onPick(event: Event): void {
    const input = event.target as HTMLInputElement;
    this.add(Array.from(input.files ?? []));
    // Permet de choisir à nouveau le même fichier après l'avoir retiré.
    input.value = '';
  }

  onDragOver(event: DragEvent): void {
    event.preventDefault();
    this.dragging.set(this.canAdd());
  }

  onDrop(event: DragEvent): void {
    event.preventDefault();
    this.dragging.set(false);
    this.add(Array.from(event.dataTransfer?.files ?? []));
  }

  add(files: File[]): void {
    this.error.set(null);

    if (0 === files.length) {
      return;
    }
    if (!this.noPeople()) {
      this.error.set("Cochez d'abord la case : aucune personne ne doit apparaître sur les photos.");
      return;
    }

    const room = MAX_PHOTOS - this.total();
    const kept = files.slice(0, Math.max(0, room));

    if (kept.length < files.length) {
      this.error.set(
        `${MAX_PHOTOS} photos maximum par logement : ${files.length - kept.length} photo(s) non ajoutée(s).`,
      );
    }

    this.queue.update((queue) => [...queue, ...kept.map((file) => this.toUpload(file))]);
    this.start();
  }

  retry(upload: Upload): void {
    this.patch(upload.key, { error: null, progress: 0 });
    this.start();
  }

  discard(upload: Upload): void {
    URL.revokeObjectURL(upload.preview);
    this.queue.update((queue) => queue.filter((one) => one.key !== upload.key));
  }

  move(index: number, delta: number): void {
    const list = [...this.photos()];
    const target = index + delta;

    if (target < 0 || target >= list.length) {
      return;
    }

    [list[index], list[target]] = [list[target], list[index]];
    this.reorder(list);
  }

  makeCover(index: number): void {
    const list = [...this.photos()];
    const [cover] = list.splice(index, 1);
    this.reorder([cover, ...list]);
  }

  remove(photo: OwnerPhoto): void {
    this.busy.set(true);
    this.ensureSaved()().subscribe((slug) =>
      this.service.deletePhoto(slug, photo.id).subscribe({
        next: () => {
          this.confirming.set(null);
          this.busy.set(false);
          this.photos.update((photos) => photos.filter((one) => one.id !== photo.id));
          this.photosChange.emit(this.photos());
        },
        error: (error: HttpErrorResponse) => {
          this.busy.set(false);
          this.error.set(apiErrorMessage(error, "La photo n'a pas pu être supprimée. Réessayez."));
        },
      }),
    );
  }

  /** L'ordre change tout de suite à l'écran ; en cas de refus, on revient à l'ancien. */
  private reorder(list: OwnerPhoto[]): void {
    const previous = this.photos();

    this.photos.set(list);
    this.photosChange.emit(list);
    this.busy.set(true);
    this.error.set(null);

    this.ensureSaved()().subscribe((slug) =>
      this.service.reorderPhotos(slug, list.map((photo) => photo.id)).subscribe({
        next: (saved) => {
          this.busy.set(false);
          this.photos.set(saved);
        },
        error: (error: HttpErrorResponse) => {
          this.busy.set(false);
          this.photos.set(previous);
          this.photosChange.emit(previous);
          this.error.set(apiErrorMessage(error, "L'ordre des photos n'a pas pu être enregistré."));
        },
      }),
    );
  }

  /** Les photos partent une par une, dans l'ordre choisi : l'ordre d'arrivée est celui d'affichage. */
  private start(): void {
    if (this.sending || !this.queue().some((upload) => this.isWaiting(upload))) {
      return;
    }

    this.sending = true;

    this.ensureSaved()().subscribe({
      next: (slug) => this.next(slug),
      error: (error: unknown) => {
        this.sending = false;
        this.error.set(
          error instanceof Error
            ? error.message
            : "L'annonce n'a pas pu être enregistrée : vos photos n'ont pas été envoyées.",
        );
        this.queue.update((queue) =>
          queue.map((upload) =>
            this.isWaiting(upload) ? { ...upload, error: 'Pas encore envoyée.' } : upload,
          ),
        );
      },
    });
  }

  private next(slug: string): void {
    const upload = this.queue().find((one) => this.isWaiting(one));

    if (!upload) {
      this.sending = false;
      return;
    }

    this.patch(upload.key, { sending: true });

    this.service.uploadPhoto(slug, upload.file).subscribe({
      next: (event) => {
        if (HttpEventType.UploadProgress === event.type && event.total) {
          this.patch(upload.key, { progress: Math.round((100 * event.loaded) / event.total) });
        }
        if (HttpEventType.Response === event.type && event.body) {
          const photo = event.body;
          this.discard(upload);
          this.photos.update((photos) => [...photos, photo]);
          this.photosChange.emit(this.photos());
        }
      },
      error: (error: HttpErrorResponse) => {
        this.patch(upload.key, {
          sending: false,
          error: apiErrorMessage(error, "L'envoi a échoué. Vérifiez votre connexion et réessayez."),
        });
        this.next(slug);
      },
      complete: () => this.next(slug),
    });
  }

  private isWaiting(upload: Upload): boolean {
    return !upload.sending && null === upload.error;
  }

  private toUpload(file: File): Upload {
    const upload: Upload = {
      key: this.nextKey++,
      file,
      preview: URL.createObjectURL(file),
      progress: 0,
      sending: false,
      error: null,
      retryable: true,
    };

    if (!ACCEPTED.includes(file.type)) {
      return { ...upload, error: 'Format non accepté : JPEG, PNG ou WebP.', retryable: false };
    }
    if (file.size > MAX_BYTES) {
      return { ...upload, error: 'Photo trop lourde : 10 Mo maximum.', retryable: false };
    }

    return upload;
  }

  private patch(key: number, changes: Partial<Upload>): void {
    this.queue.update((queue) => queue.map((upload) => (upload.key === key ? { ...upload, ...changes } : upload)));
  }
}
