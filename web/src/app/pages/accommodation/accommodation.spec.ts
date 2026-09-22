import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { RouterTestingHarness } from '@angular/router/testing';

import { AuthService } from '../../core/services/auth';
import { NavigationOrigin } from '../../core/services/navigation-origin';
import { AccommodationPage } from './accommodation';

describe('AccommodationPage', () => {
  let http: HttpTestingController;

  const listing = {
    slug: 'bungalow-hawai',
    resort: 'chm',
    type: 'bungalow',
    district: 'Hawaï',
    capacity: 4,
    maxCapacity: 4,
    bedrooms: 2,
    surface: 69,
    amenities: [],
    description: 'Un bungalow.',
    priceFrom: null,
    availability: [],
    pricePeriods: [],
    cover: null,
    photos: [],
    calendarUpToDate: true,
  };

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        provideRouter([{ path: 'logement/:slug', component: AccommodationPage }]),
        provideHttpClient(),
        provideHttpClientTesting(),
      ],
    });
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  async function open(): Promise<RouterTestingHarness> {
    const harness = await RouterTestingHarness.create();
    await harness.navigateByUrl('/logement/bungalow-hawai', AccommodationPage);
    http.expectOne((r) => r.url.endsWith('/api/accommodations/bungalow-hawai')).flush(listing);
    http
      .expectOne((r) => r.url.endsWith('/availability'))
      .flush({ slug: 'bungalow-hawai', busy: [] });
    harness.detectChanges();

    return harness;
  }

  it('shows the booking form to a visitor, even after opening it from the owner space in the same tab', async () => {
    // The owner opened it from his space, then logged out: the flag is still in memory.
    TestBed.inject(NavigationOrigin).openedFromOwnerSpace('bungalow-hawai');

    const harness = await open();

    expect(harness.routeNativeElement!.querySelector('cm-booking-form')).not.toBeNull();
    expect(harness.routeNativeElement!.textContent).not.toContain('Aperçu de votre annonce');
    expect(harness.routeNativeElement!.textContent).not.toContain('Retour à mes logements');
  });

  it('shows the preview to the owner, whatever page he came from', async () => {
    TestBed.inject(AuthService).login('alice@example.com', 'x').subscribe();
    http
      .expectOne((r) => r.url.endsWith('/api/login'))
      .flush({ displayName: 'Alice', verified: true });

    const harness = await open();
    http
      .expectOne((r) => r.url.endsWith('/api/owner/accommodations/bungalow-hawai'))
      .flush({ slug: 'bungalow-hawai' });
    harness.detectChanges();

    expect(harness.routeNativeElement!.textContent).toContain('Aperçu de votre annonce');
    expect(harness.routeNativeElement!.querySelector('cm-booking-form')).toBeNull();
  });

  it('shows the booking form to another logged-in owner', async () => {
    TestBed.inject(AuthService).login('bob@example.com', 'x').subscribe();
    http
      .expectOne((r) => r.url.endsWith('/api/login'))
      .flush({ displayName: 'Bob', verified: true });

    const harness = await open();
    http
      .expectOne((r) => r.url.endsWith('/api/owner/accommodations/bungalow-hawai'))
      .flush(null, { status: 404, statusText: 'Not Found' });
    harness.detectChanges();

    expect(harness.routeNativeElement!.querySelector('cm-booking-form')).not.toBeNull();
  });
});
