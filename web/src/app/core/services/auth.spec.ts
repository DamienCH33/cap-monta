import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import { Owner } from '../models/owner';
import { AuthService } from './auth';

const owner: Owner = {
  email: 'proprietaire@example.com',
  displayName: 'Damien C.',
  phone: null,
  verified: true,
};

describe('AuthService', () => {
  let auth: AuthService;
  let httpMock: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting()],
    });

    auth = TestBed.inject(AuthService);
    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => httpMock.verify());

  it('retient le propriétaire après une connexion réussie', () => {
    auth.login('proprietaire@example.com', 'motdepasse').subscribe();
    httpMock.expectOne((req) => req.url.endsWith('/api/login')).flush(owner);

    expect(auth.currentOwner()).toEqual(owner);
    expect(auth.isLoggedIn()).toBe(true);
  });

  it("l'oublie à la déconnexion", () => {
    auth.login('proprietaire@example.com', 'motdepasse').subscribe();
    httpMock.expectOne((req) => req.url.endsWith('/api/login')).flush(owner);

    auth.logout().subscribe();
    httpMock.expectOne((req) => req.url.endsWith('/api/logout')).flush(null);

    expect(auth.currentOwner()).toBeNull();
    expect(auth.isLoggedIn()).toBe(false);
  });

  it('traite un 401 sur /owner/me comme une réponse, pas comme une panne', () => {
    let errored = false;
    let received: Owner | null | undefined;

    auth.restore().subscribe({
      next: (value) => (received = value),
      error: () => (errored = true),
    });

    httpMock
      .expectOne((req) => req.url.endsWith('/api/owner/me'))
      .flush(null, { status: 401, statusText: 'Unauthorized' });

    expect(errored).toBe(false);
    expect(received).toBeNull();
    expect(auth.sessionChecked()).toBe(true);
  });

  it('ne redemande pas la session une fois qu’elle est connue', () => {
    auth.restore().subscribe();
    httpMock.expectOne((req) => req.url.endsWith('/api/owner/me')).flush(owner);

    // Le second appel ne doit déclencher aucune requête : c'est le afterEach qui l'atteste.
    auth.restore().subscribe();

    expect(auth.currentOwner()).toEqual(owner);
  });
});
