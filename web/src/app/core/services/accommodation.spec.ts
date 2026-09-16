import { provideHttpClient } from '@angular/common/http';
import { TestBed } from '@angular/core/testing';

import { AccommodationService } from './accommodation';

describe('AccommodationService', () => {
  let service: AccommodationService;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient()],
    });

    service = TestBed.inject(AccommodationService);
  });

  it('est instanciable', () => {
    expect(service).toBeTruthy();
  });
});
