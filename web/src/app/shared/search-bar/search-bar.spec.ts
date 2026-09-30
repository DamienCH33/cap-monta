import { Component } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter, Router } from '@angular/router';

import { SearchBar } from './search-bar';

@Component({ template: '' })
class Blank {}

describe('SearchBar', () => {
  it('opens the search page when the form is sent', async () => {
    TestBed.configureTestingModule({
      providers: [provideRouter([{ path: 'recherche', component: Blank }])],
    });
    const fixture = TestBed.createComponent(SearchBar);
    fixture.detectChanges();
    const router = TestBed.inject(Router);

    const form: HTMLFormElement = fixture.nativeElement.querySelector('form');
    const event = new Event('submit', { cancelable: true });
    form.dispatchEvent(event);
    await fixture.whenStable();

    // Le navigateur ne doit pas envoyer le formulaire lui-même (rechargement de l'accueil).
    expect(event.defaultPrevented).toBe(true);
    expect(router.url).toBe('/recherche');
  });
});
