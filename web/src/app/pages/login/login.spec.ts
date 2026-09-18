import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { ActivatedRoute, convertToParamMap, provideRouter } from '@angular/router';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import { Login } from './login';

describe('Login', () => {
  let httpMock: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([]),
        {
          provide: ActivatedRoute,
          useValue: { snapshot: { queryParamMap: convertToParamMap({ suite: '/mon-espace' }) } },
        },
      ],
    });

    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => httpMock.verify());

  it('explique pourquoi on demande de se connecter quand on a été redirigé', () => {
    const fixture = TestBed.createComponent(Login);
    fixture.detectChanges();

    expect((fixture.nativeElement as HTMLElement).textContent).toContain(
      'Connectez-vous pour accéder à cette page.',
    );
  });

  it('reste vague et conserve la saisie quand la connexion échoue', () => {
    const fixture = TestBed.createComponent(Login);
    fixture.detectChanges();

    fixture.componentInstance.form.setValue({ email: 'a@b.fr', password: 'motdepasse' });
    fixture.componentInstance.submit();

    httpMock
      .expectOne((req) => req.url.endsWith('/api/login'))
      .flush(null, { status: 401, statusText: 'Unauthorized' });

    fixture.detectChanges();

    const text = (fixture.nativeElement as HTMLElement).textContent ?? '';

    expect(text).toContain('Adresse ou mot de passe incorrect.');

    // Le formulaire n'est pas vidé : retaper son mot de passe après une faute de frappe est pénible.
    expect(fixture.componentInstance.form.getRawValue()).toEqual({
      email: 'a@b.fr',
      password: 'motdepasse',
    });
  });

  it("n'appelle pas l'API tant que le formulaire est incomplet", () => {
    const fixture = TestBed.createComponent(Login);
    fixture.detectChanges();

    fixture.componentInstance.submit();
    fixture.detectChanges();

    // Aucune requête : le afterEach le vérifie. Et l'utilisateur voit pourquoi.
    expect((fixture.nativeElement as HTMLElement).textContent).toContain(
      'Indiquez votre adresse email.',
    );
  });
});
