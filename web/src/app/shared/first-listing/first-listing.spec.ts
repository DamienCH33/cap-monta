import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';

import { environment } from '../../../environments/environment';
import { FirstListing } from './first-listing';

describe('FirstListing', () => {
  function render(available: boolean): HTMLElement {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting(), provideRouter([])],
    });
    const fixture = TestBed.createComponent(FirstListing);
    fixture.detectChanges();
    TestBed.inject(HttpTestingController)
      .expectOne(`${environment.apiUrl}/api/owner/listing-imports/assistant`)
      .flush({ available });
    fixture.detectChanges();

    return fixture.nativeElement;
  }

  it('offers the import first when the assistant can read', () => {
    const links = [...render(true).querySelectorAll<HTMLAnchorElement>('.first__choice')];

    expect(links.map((link) => link.getAttribute('href'))).toEqual([
      '/mon-espace/importer',
      '/mon-espace/logements/nouveau',
    ]);
  });

  it('only offers to start from scratch when the assistant is unavailable', () => {
    const links = [...render(false).querySelectorAll<HTMLAnchorElement>('.first__choice')];

    expect(links.map((link) => link.getAttribute('href'))).toEqual([
      '/mon-espace/logements/nouveau',
    ]);
  });
});
