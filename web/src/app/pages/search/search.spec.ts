import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { RouterTestingHarness } from '@angular/router/testing';

import { Search } from './search';

describe('Search', () => {
  let httpMock: HttpTestingController;

  const isSearch = (url: string) => url.endsWith('/api/accommodations');
  const isSuggest = (url: string) => url.endsWith('/api/stay-suggestions');

  const suggestions = {
    member: [
      { arrival: '2026-10-05', departure: '2026-10-12', availableCount: 2 },
      { arrival: '2026-10-19', departure: '2026-10-26', availableCount: 1 },
    ],
  };

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      providers: [
        provideRouter([{ path: 'recherche', component: Search }]),
        provideHttpClient(),
        provideHttpClientTesting(),
      ],
    }).compileComponents();

    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    httpMock.verify();
  });

  async function open(url: string): Promise<RouterTestingHarness> {
    const harness = await RouterTestingHarness.create();
    await harness.navigateByUrl(url, Search);

    httpMock.expectOne((req) => req.url.endsWith('/api/districts')).flush({ member: [] });

    return harness;
  }

  it('se construit', async () => {
    const harness = await open('/recherche');
    httpMock.expectOne((req) => isSearch(req.url)).flush({ member: [] });

    expect(harness.routeDebugElement?.componentInstance).toBeTruthy();
  });

  it('propose des dates proches quand rien n’est libre', async () => {
    const harness = await open('/recherche?arrivee=2026-10-12&depart=2026-10-19&quartier=Europa');

    httpMock.expectOne((req) => isSearch(req.url)).flush({ member: [] });

    const request = httpMock.expectOne((req) => isSuggest(req.url));
    expect(request.request.params.get('arrival')).toBe('2026-10-12');
    expect(request.request.params.get('district')).toBe('Europa');
    request.flush(suggestions);

    harness.detectChanges();

    const chips: HTMLAnchorElement[] = Array.from(
      harness.routeNativeElement!.querySelectorAll('a.suggestions__chip'),
    );
    expect(chips.length).toBe(2);

    const href = chips[0].getAttribute('href') ?? '';
    expect(href).toContain('arrivee=2026-10-05');
    expect(href).toContain('depart=2026-10-12');
    expect(href).toContain('quartier=Europa');
  });

  it('ne demande pas de suggestions quand la recherche trouve des logements', async () => {
    await open('/recherche?arrivee=2026-10-12&depart=2026-10-19');

    httpMock.expectOne((req) => isSearch(req.url)).flush({ member: [{ slug: 'un-logement' }] });

    httpMock.expectNone((req) => isSuggest(req.url));
  });

  it('ne demande pas de suggestions sans dates', async () => {
    await open('/recherche?quartier=Europa');

    httpMock.expectOne((req) => isSearch(req.url)).flush({ member: [] });

    httpMock.expectNone((req) => isSuggest(req.url));
  });

  it('affiche le message sans puces si les suggestions échouent', async () => {
    const harness = await open('/recherche?arrivee=2026-10-12&depart=2026-10-19');

    httpMock.expectOne((req) => isSearch(req.url)).flush({ member: [] });
    httpMock
      .expectOne((req) => isSuggest(req.url))
      .flush('Erreur', { status: 500, statusText: 'Server Error' });

    harness.detectChanges();

    const page = harness.routeNativeElement!;
    expect(page.textContent).toContain('Aucun logement libre');
    expect(page.querySelectorAll('a.suggestions__chip').length).toBe(0);
  });
});
