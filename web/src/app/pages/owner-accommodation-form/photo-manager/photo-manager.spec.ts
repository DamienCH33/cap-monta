import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { of } from 'rxjs';

import { environment } from '../../../../environments/environment';
import { OwnerPhoto } from '../../../core/models/owner-accommodation';
import { PhotoManager } from './photo-manager';

describe('PhotoManager', () => {
  const api = `${environment.apiUrl}/api/owner/accommodations/mobil-home/photos`;
  let manager: PhotoManager;
  let http: HttpTestingController;

  const photo = (id: string): OwnerPhoto => ({
    id,
    url: `/${id}.webp`,
    thumbUrl: `/${id}-thumb.webp`,
    width: 1600,
    height: 1066,
  });

  const jpeg = (name = 'photo.jpg'): File => new File(['x'], name, { type: 'image/jpeg' });

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting()],
    });

    const fixture = TestBed.createComponent(PhotoManager);
    fixture.componentRef.setInput('ensureSaved', () => of('mobil-home'));
    fixture.componentRef.setInput('initial', [photo('a'), photo('b')]);
    manager = fixture.componentInstance;
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  it('sends nothing until the no-people box is ticked', () => {
    manager.add([jpeg()]);

    expect(manager.error()).toContain('Cochez');
    expect(manager.queue()).toEqual([]);
    http.expectNone(api);
  });

  it('refuses a wrong format before sending anything', () => {
    manager.noPeople.set(true);
    manager.add([new File(['x'], 'plan.pdf', { type: 'application/pdf' })]);

    expect(manager.queue()[0].error).toContain('JPEG, PNG ou WebP');
    expect(manager.queue()[0].retryable).toBe(false);
    http.expectNone(api);
  });

  it('sends the photos one by one and adds them after the others', () => {
    manager.noPeople.set(true);
    manager.add([jpeg('1.jpg'), jpeg('2.jpg')]);

    // Une seule requête à la fois : la seconde attend la fin de la première.
    http.expectOne(api).flush(photo('c'));
    http.expectOne(api).flush(photo('d'));

    expect(manager.photos().map((one) => one.id)).toEqual(['a', 'b', 'c', 'd']);
    expect(manager.queue()).toEqual([]);
  });

  it('puts a photo first when it becomes the cover', () => {
    manager.makeCover(1);

    expect(manager.photos().map((one) => one.id)).toEqual(['b', 'a']);
    const request = http.expectOne(`${api}/order`);
    expect(request.request.body).toEqual({ ids: ['b', 'a'] });
    request.flush([photo('b'), photo('a')]);
  });

  it('goes back to the previous order when the API refuses', () => {
    manager.move(0, 1);
    http.expectOne(`${api}/order`).flush({ detail: 'Refus' }, { status: 422, statusText: 'Unprocessable' });

    expect(manager.photos().map((one) => one.id)).toEqual(['a', 'b']);
    expect(manager.error()).toBe('Refus');
  });

  it('never accepts more than twelve photos', () => {
    manager.noPeople.set(true);
    manager.add(Array.from({ length: 12 }, (_, i) => jpeg(`${i}.jpg`)));

    expect(manager.total()).toBe(12);
    expect(manager.error()).toContain('2 photo(s) non ajoutée(s)');
    http.expectOne(api);
  });
});
