import { TestBed } from '@angular/core/testing';

import { Photo } from '../../../core/models/accommodation';
import { PhotoGallery } from './photo-gallery';

describe('PhotoGallery', () => {
  const photo = (name: string, width = 1600): Photo => ({
    url: `/${name}.webp`,
    thumbUrl: `/${name}-thumb.webp`,
    width,
    height: 1066,
  });

  const create = (photos: Photo[]) => {
    const fixture = TestBed.createComponent(PhotoGallery);
    fixture.componentRef.setInput('photos', photos);
    fixture.componentRef.setInput('label', 'Mobil-home Europa');
    fixture.detectChanges();

    return fixture;
  };

  it('shows the cover first, full size', () => {
    const fixture = create([photo('a'), photo('b')]);
    const images = (fixture.nativeElement as HTMLElement).querySelectorAll('.gallery__img');

    expect(images.length).toBe(2);
    expect(images[0].getAttribute('src')).toBe('/a.webp');
    expect(images[1].getAttribute('src')).toBe('/b-thumb.webp');
    expect(images[0].getAttribute('alt')).toBe('Mobil-home Europa, photo 1 sur 2');
  });

  it('announces the photos that do not fit in the mosaic', () => {
    const fixture = create(['a', 'b', 'c', 'd', 'e', 'f', 'g'].map((name) => photo(name)));
    const more = (fixture.nativeElement as HTMLElement).querySelector('.gallery__more');

    expect(more?.textContent?.trim()).toBe('+2');
  });

  it('goes round from the last photo to the first, and back', () => {
    const gallery = create([photo('a'), photo('b'), photo('c')]).componentInstance;

    gallery.previous();
    expect(gallery.current()).toBe(2);

    gallery.next();
    expect(gallery.current()).toBe(0);
  });

  it('never announces a thumbnail wider than the picture itself', () => {
    const gallery = create([photo('a', 400)]).componentInstance;

    expect(gallery.srcset(photo('a', 400))).toBe('/a-thumb.webp 400w, /a.webp 400w');
  });
});
