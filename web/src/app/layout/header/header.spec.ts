import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { Component } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter, Router } from '@angular/router';

import { Header } from './header';

@Component({ template: '' })
class Blank {}

describe('Header', () => {
  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([{ path: 'recherche', component: Blank }]),
      ],
    });
  });

  it('folds the mobile menu back once a link is followed', async () => {
    const fixture = TestBed.createComponent(Header);
    const header = fixture.componentInstance;
    TestBed.inject(HttpTestingController).match(() => true);

    header.toggleMenu();
    expect(header.menuOpen()).toBe(true);

    await TestBed.inject(Router).navigateByUrl('/recherche');

    expect(header.menuOpen()).toBe(false);
  });
});
