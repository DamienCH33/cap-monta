import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import {
  ActivatedRouteSnapshot,
  provideRouter,
  RouterStateSnapshot,
  UrlTree,
} from '@angular/router';
import { firstValueFrom, Observable } from 'rxjs';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import { Owner } from '../models/owner';
import { ownerGuard } from './owner-guard';

const owner: Owner = {
  email: 'proprietaire@example.com',
  displayName: 'Damien C.',
  phone: null,
  verified: true,
};

describe('ownerGuard', () => {
  let httpMock: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting(), provideRouter([])],
    });

    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => httpMock.verify());

  function guard(url: string): Observable<boolean | UrlTree> {
    return TestBed.runInInjectionContext(() =>
      ownerGuard({} as ActivatedRouteSnapshot, { url } as RouterStateSnapshot),
    ) as Observable<boolean | UrlTree>;
  }

  it('renvoie un visiteur anonyme vers la connexion en mémorisant sa destination', async () => {
    const result = firstValueFrom(guard('/mon-espace'));

    httpMock
      .expectOne((req) => req.url.endsWith('/api/owner/me'))
      .flush(null, { status: 401, statusText: 'Unauthorized' });

    const value = await result;

    expect(value).toBeInstanceOf(UrlTree);

    const tree = value as UrlTree;
    expect(tree.toString()).toContain('/connexion');
    expect(tree.queryParams['suite']).toBe('/mon-espace');
  });

  it('laisse passer un propriétaire connecté', async () => {
    const result = firstValueFrom(guard('/mon-espace'));

    httpMock.expectOne((req) => req.url.endsWith('/api/owner/me')).flush(owner);

    expect(await result).toBe(true);
  });
});
