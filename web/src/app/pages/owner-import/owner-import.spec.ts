import { TestBed } from '@angular/core/testing';
import { ActivatedRoute, convertToParamMap, provideRouter } from '@angular/router';
import { of, throwError } from 'rxjs';
import { HttpErrorResponse } from '@angular/common/http';

import { ListingImportView } from '../../core/models/listing-import';
import { ApplyPayload, ListingImportService } from '../../core/services/listing-import';
import { OwnerAccommodationService } from '../../core/services/owner-accommodation';
import { OwnerImport } from './owner-import';

describe('OwnerImport', () => {
  const done: ListingImportView = {
    id: 'lecture-1',
    status: 'done',
    failure: null,
    message: null,
    attempts: 1,
    text: 'Mobil-home La Lande. Juillet 650 € la semaine. Septembre 420 €.',
    appliedTo: null,
    createdAt: '2026-09-24T10:00:00+02:00',
    proposal: {
      listing: {
        resort: 'chm',
        type: 'mobile_home',
        capacity: null,
        bedrooms: 2,
        surface: null,
        district: 'la-lande',
        amenities: ['television'],
        petsPolicy: null,
        otherFeatures: ['transats'],
        description: 'Mobil-home La Lande.',
      },
      periods: [
        {
          label: 'juillet',
          start: '2027-07-01',
          end: '2027-08-01',
          weeklyPrice: 65000,
          nightlyPrice: null,
          minimumNights: 1,
          saturdayArrival: false,
          stayPrice: null,
          unitToConfirm: false,
          datesMissing: false,
          past: false,
          tooFar: false,
          selected: true,
        },
        {
          label: 'septembre',
          start: '2027-09-01',
          end: '2027-10-01',
          weeklyPrice: 42000,
          nightlyPrice: null,
          minimumNights: 1,
          saturdayArrival: false,
          stayPrice: null,
          unitToConfirm: true,
          datesMissing: false,
          past: false,
          tooFar: false,
          selected: true,
        },
      ],
      unavailable: [],
      questions: ['420 € pour « septembre » : est-ce le prix de la semaine ?'],
      contacts: [],
    },
  };

  let sent: ApplyPayload | null;
  let applyResult: ReturnType<ListingImportService['apply']>;

  function create(): OwnerImport {
    sent = null;
    TestBed.configureTestingModule({
      providers: [
        provideRouter([]),
        {
          provide: ActivatedRoute,
          useValue: { snapshot: { queryParamMap: convertToParamMap({}) } },
        },
        {
          provide: ListingImportService,
          useValue: {
            assistant: () => of({ available: true, reason: null, until: null }),
            create: () => of({ ...done, status: 'pending', proposal: null }),
            watch: () => of(done),
            apply: (_id: string, payload: ApplyPayload) => {
              sent = payload;
              return applyResult;
            },
          },
        },
        {
          provide: OwnerAccommodationService,
          useValue: { districts: () => of([]), get: () => of(null) },
        },
      ],
    });

    return TestBed.createComponent(OwnerImport).componentInstance;
  }

  it('sends nothing until the text is long enough', () => {
    const page = create();

    page.text.set('Bungalow');
    expect(page.canSend()).toBe(false);

    page.text.set(done.text);
    expect(page.canSend()).toBe(true);
  });

  it('reads, then fills the check screen with what the API prepared', () => {
    const page = create();
    page.text.set(done.text);

    page.read();

    expect(page.step()).toBe('check');
    expect(page.listing()).toMatchObject({
      resort: 'chm',
      district: 'la-lande',
      petsPolicy: 'on_request',
    });
    expect(page.rates().map((row) => row.weekly)).toEqual([650, 420]);
    expect(page.rates()[1].hints[0]).toContain('semaine ou la nuit');
  });

  it('saves a new draft in cents, and shows a refusal on the row it concerns', () => {
    const page = create();
    page.text.set(done.text);
    page.read();
    page.patchRate(0, { keep: false });
    applyResult = throwError(
      () =>
        new HttpErrorResponse({
          status: 422,
          error: {
            message: 'Certaines lignes sont à corriger : rien n’a été enregistré.',
            violations: [
              { propertyPath: 'periods[0].end', message: 'Cette période est entièrement passée.' },
            ],
          },
        }),
    );

    page.save();

    expect(sent?.slug).toBeUndefined();
    expect(sent?.accommodation).toMatchObject({ type: 'mobile_home', district: 'la-lande' });
    expect(sent?.periods).toEqual([
      {
        start: '2027-09-01',
        end: '2027-10-01',
        weeklyPrice: 42000,
        nightlyPrice: null,
        minimumNights: 1,
        saturdayArrival: false,
      },
    ]);
    expect(page.rateErrors().get(1)).toEqual(['Cette période est entièrement passée.']);
    expect(page.error()).toContain('rien n’a été enregistré');
  });
});
