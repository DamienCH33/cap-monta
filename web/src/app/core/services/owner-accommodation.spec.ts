import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';

import { environment } from '../../../environments/environment';
import { OwnerAccommodationService } from './owner-accommodation';

describe('OwnerAccommodationService', () => {
  const api = `${environment.apiUrl}/api/owner/accommodations`;
  let service: OwnerAccommodationService;
  let http: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting()],
    });
    service = TestBed.inject(OwnerAccommodationService);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  it('lists the owner accommodations with the session cookie', () => {
    let slugs: string[] = [];
    service.list().subscribe((list) => (slugs = list.map((item) => item.slug)));

    const request = http.expectOne(api);
    expect(request.request.withCredentials).toBe(true);
    request.flush({ member: [{ slug: 'mobil-home-europa-6-personnes' }], totalItems: 1 });

    expect(slugs).toEqual(['mobil-home-europa-6-personnes']);
  });

  it('creates a draft with the session cookie', () => {
    service.create({ resort: 'chm', type: 'mobile_home', capacity: 6, bedrooms: 2 }).subscribe();

    const request = http.expectOne(api);
    expect(request.request.method).toBe('POST');
    expect(request.request.withCredentials).toBe(true);
    expect(request.request.body).toEqual({
      resort: 'chm',
      type: 'mobile_home',
      capacity: 6,
      bedrooms: 2,
    });
    request.flush({});
  });

  it('publishes through its own endpoint, without a body', () => {
    service.publish('mobil-home').subscribe();

    const request = http.expectOne(`${api}/mobil-home/publish`);
    expect(request.request.method).toBe('POST');
    expect(request.request.body).toBeNull();
    expect(request.request.withCredentials).toBe(true);
    request.flush({});
  });

  it('sends only the changes, as a merge patch', () => {
    service.update('mobil-home', { capacity: 6 }).subscribe();

    const request = http.expectOne(`${api}/mobil-home`);
    expect(request.request.method).toBe('PATCH');
    expect(request.request.headers.get('Content-Type')).toBe('application/merge-patch+json');
    expect(request.request.body).toEqual({ capacity: 6 });
    request.flush({});
  });

  it('reads the districts from the public list', () => {
    let names: string[] = [];
    service.districts().subscribe((list) => (names = list.map((district) => district.name)));

    http
      .expectOne(`${environment.apiUrl}/api/districts`)
      .flush({ member: [{ slug: 'europa', name: 'Europa', resort: 'chm' }], totalItems: 1 });

    expect(names).toEqual(['Europa']);
  });
});
