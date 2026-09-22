import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';

import { environment } from '../../../environments/environment';
import { OwnerBookingRequest } from '../../core/models/booking-answer';
import { OwnerRequests } from './owner-requests';

describe('OwnerRequests', () => {
  const api = `${environment.apiUrl}/api/owner/booking-requests`;
  let page: OwnerRequests;
  let http: HttpTestingController;

  const request = (
    id: string,
    overrides: Partial<OwnerBookingRequest> = {},
  ): OwnerBookingRequest => ({
    id,
    status: 'pending',
    accommodation: { slug: 'bungalow', title: 'Bungalow · Europa' },
    start: '2099-07-10',
    end: '2099-07-17',
    nights: 7,
    adults: 2,
    children: 0,
    infants: 0,
    pets: 0,
    estimatedPrice: 72150,
    agreedPrice: null,
    ownerMessage: null,
    createdAt: '2099-06-01T10:00:00+02:00',
    expiresAt: '2099-06-03T10:00:00+02:00',
    respondedAt: null,
    started: false,
    guestName: 'Jeanne',
    message: null,
    outsideRules: false,
    conflict: false,
    contact: null,
    ...overrides,
  });

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting(), provideRouter([])],
    });

    page = TestBed.createComponent(OwnerRequests).componentInstance;
    http = TestBed.inject(HttpTestingController);
    http
      .expectOne(api)
      .flush([
        request('a'),
        request('b', { estimatedPrice: null, start: '2099-08-01', end: '2099-08-08' }),
      ]);
  });

  afterEach(() => http.verify());

  it('never sends a price when the rates set it', () => {
    const [first] = page.visible();
    page.start(first, 'accept');
    // Even if something typed one: the rates price is the one the guest saw.
    page.price.set(500);

    page.confirm(first, 'accept');

    const call = http.expectOne(`${api}/a/accept`);
    expect(call.request.body).toEqual({ price: null, message: null });
    call.flush([request('a', { status: 'accepted', agreedPrice: 72150 }), request('b')]);

    expect(page.notice()).toContain('acceptée');
    expect(page.tabs()[1].count).toBe(1);
  });

  it('asks for a price before sending when there was no rate', () => {
    const toPrice = page.visible().find((r) => 'b' === r.id)!;
    page.start(toPrice, 'accept');

    page.confirm(toPrice, 'accept');
    expect(page.error()).toContain('prix');

    page.price.set(480);
    page.confirm(toPrice, 'accept');
    expect(http.expectOne(`${api}/b/accept`).request.body).toEqual({ price: 48000, message: null });
  });
});
