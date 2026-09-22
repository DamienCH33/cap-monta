import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';

import { environment } from '../../../environments/environment';
import { AuthService } from '../../core/services/auth';
import { FRENCH_PHONE, OwnerProfile } from './owner-profile';

describe('OwnerProfile', () => {
  const api = `${environment.apiUrl}/api`;
  let page: OwnerProfile;
  let http: HttpTestingController;
  let auth: AuthService;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting(), provideRouter([])],
    });

    http = TestBed.inject(HttpTestingController);
    auth = TestBed.inject(AuthService);
    // Session ouverte : le formulaire part des valeurs du propriétaire connecté.
    auth.login('alice@example.com', 'x').subscribe();
    http
      .expectOne(`${api}/login`)
      .flush({ email: 'alice@example.com', displayName: 'Alice', phone: null, verified: true });

    page = TestBed.createComponent(OwnerProfile).componentInstance;
  });

  afterEach(() => http.verify());

  it('accepts the phone numbers the API accepts', () => {
    for (const phone of ['06 12 34 56 78', '0612345678', '+33 6 12 34 56 78', '05.56.12.34.56']) {
      expect(FRENCH_PHONE.test(phone)).toBe(true);
    }
    for (const phone of ['12', '06 12 34 56', '+44 20 7946 0958']) {
      expect(FRENCH_PHONE.test(phone)).toBe(false);
    }
  });

  it('starts from the connected owner and updates the session after saving', () => {
    expect(page.profile.getRawValue()).toEqual({ displayName: 'Alice', phone: '' });

    page.profile.setValue({ displayName: 'Alice Martin', phone: '06 12 34 56 78' });
    page.saveProfile();

    const request = http.expectOne(`${api}/owner/me`);
    expect(request.request.method).toBe('PATCH');
    request.flush({
      email: 'alice@example.com',
      displayName: 'Alice Martin',
      phone: '06 12 34 56 78',
      verified: true,
    });

    expect(page.profileState()).toBe('saved');
    expect(auth.currentOwner()?.displayName).toBe('Alice Martin');
  });

  it('sends nothing while the form is invalid', () => {
    page.profile.setValue({ displayName: 'A', phone: '12' });
    page.saveProfile();

    expect(page.showProfileError('displayName')).toBe(true);
    expect(page.showProfileError('phone')).toBe(true);
    http.expectNone(`${api}/owner/me`);
  });

  it('shows the API refusal of a wrong current password, then clears the form on success', () => {
    page.password.setValue({ currentPassword: 'faux', newPassword: 'nouveau-mot-de-passe' });
    page.savePassword();
    http
      .expectOne(`${api}/owner/me/password`)
      .flush(
        {
          violations: [
            { propertyPath: 'currentPassword', title: 'Mot de passe actuel incorrect.' },
          ],
        },
        { status: 422, statusText: 'Unprocessable Entity' },
      );
    expect(page.passwordError()).toBe('Mot de passe actuel incorrect.');

    page.password.setValue({ currentPassword: 'bon', newPassword: 'nouveau-mot-de-passe' });
    page.savePassword();
    http
      .expectOne(`${api}/owner/me/password`)
      .flush(null, { status: 204, statusText: 'No Content' });

    expect(page.passwordState()).toBe('saved');
    expect(page.password.getRawValue()).toEqual({ currentPassword: '', newPassword: '' });
  });
});
