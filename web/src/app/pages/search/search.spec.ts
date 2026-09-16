import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';

import { Search } from './search';

describe('Search', () => {
  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [Search],
      providers: [provideRouter([]), provideHttpClient(), provideHttpClientTesting()],
    }).compileComponents();
  });

  it('se construit', () => {
    const fixture = TestBed.createComponent(Search);
    fixture.detectChanges();

    expect(fixture.componentInstance).toBeTruthy();
  });
});
