import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';

import { environment } from '../../../../environments/environment';
import { ReportListing } from './report-listing';

describe('ReportListing', () => {
  let http: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting()] });
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  function create(): ReportListing {
    const fixture = TestBed.createComponent(ReportListing);
    fixture.componentRef.setInput('slug', 'bungalow-europa');

    return fixture.componentInstance;
  }

  it("n'envoie rien sans raison", () => {
    const form = create();
    form.submit();

    expect(form.error()).toContain('raison');
  });

  it('envoie le signalement et remercie', () => {
    const form = create();
    form.reason.set('people_visible');
    form.message.set('  photo 3  ');
    form.submit();

    const request = http.expectOne(`${environment.apiUrl}/api/accommodations/bungalow-europa/reports`);
    expect(request.request.body).toEqual({ reason: 'people_visible', message: 'photo 3', email: null });
    request.flush(null, { status: 202, statusText: 'Accepted' });

    expect(form.sent()).toBe(true);
  });
});
