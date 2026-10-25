import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';

import { environment } from '../../../environments/environment';
import { StayAlertForm } from './stay-alert-form';

describe('StayAlertForm', () => {
  let http: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting()] });
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  function create(): StayAlertForm {
    const fixture = TestBed.createComponent(StayAlertForm);
    fixture.componentRef.setInput('arrival', '2027-07-10');
    fixture.componentRef.setInput('departure', '2027-07-17');
    fixture.componentRef.setInput('guests', 4);
    fixture.componentRef.setInput('resort', 'chm');

    return fixture.componentInstance;
  }

  it("n'envoie rien sans email valide ni consentement", () => {
    const form = create();
    form.email.set('pas-un-email');
    form.submit();
    expect(form.error()).toContain('email');

    form.email.set('anna@example.com');
    form.submit();
    expect(form.error()).toContain('case');
  });

  it("crée l'alerte avec les critères de la recherche", () => {
    const form = create();
    form.email.set('  anna@example.com ');
    form.consent.set(true);
    form.submit();

    const request = http.expectOne(`${environment.apiUrl}/api/stay-alerts`);
    expect(request.request.body).toEqual({
      email: 'anna@example.com',
      arrival: '2027-07-10',
      departure: '2027-07-17',
      guests: 4,
      resort: 'chm',
      consent: true,
    });
    request.flush(null, { status: 202, statusText: 'Accepted' });

    expect(form.sent()).toBe(true);
    expect(form.error()).toBeNull();
  });
});
