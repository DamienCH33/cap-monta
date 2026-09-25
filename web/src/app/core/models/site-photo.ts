/** Les emplacements de photo du site, remplis par `npm run photos`. */
export type SitePhotoSlot = 'hero' | 'dunes' | 'central' | 'roadside' | 'owner';

export interface SitePhoto {
  /** Largeurs produites, en pixels : /photos/site/{emplacement}-{largeur}.avif et .webp. */
  widths: number[];
  /** Largeur / hauteur de l'original. */
  ratio: number;
  alt: string;
  /** Valeur CSS object-position : la partie de la photo à garder quand elle est recadrée. */
  focus: string;
  credit: string;
  license: string | null;
  source: string | null;
}

export type SitePhotos = Partial<Record<SitePhotoSlot, SitePhoto>>;

export function sitePhotoSrcset(
  slot: SitePhotoSlot,
  photo: SitePhoto,
  format: 'avif' | 'webp',
): string {
  return photo.widths
    .map((width) => `/photos/site/${slot}-${width}.${format} ${width}w`)
    .join(', ');
}

/** La plus grande version WebP, pour l'attribut src (navigateurs sans srcset). */
export function sitePhotoFallback(slot: SitePhotoSlot, photo: SitePhoto): string {
  return `/photos/site/${slot}-${photo.widths[photo.widths.length - 1]}.webp`;
}
