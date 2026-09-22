import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { ActivatedRoute, convertToParamMap, provideRouter } from '@angular/router';

import { environment } from '../../../environments/environment';
import { OwnerCalendar as Calendar } from '../../core/models/owner-calendar';
import { OwnerCalendar } from './owner-calendar';

describe('OwnerCalendar', () => {
  const api = `${environment.apiUrl}/api/owner/accommodations/bungalow`;
  let page: OwnerCalendar;
  let http: HttpTestingController;

  const calendar = (periods: Calendar['periods'] = []): Calendar => ({
    slug: 'bungalow',
    from: '2027-07-01',
    to: '2028-12-31',
    checkedAt: null,
    upToDate: false,
    periods,
  });

  const day = (key: string, taken = false) => ({
    key,
    label: Number(key.slice(8)),
    past: false,
    period: taken ? (calendar().periods[0] ?? null) : null,
  });

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([]),
        {
          provide: ActivatedRoute,
          useValue: { snapshot: { paramMap: convertToParamMap({ slug: 'bungalow' }) } },
        },
      ],
    });

    page = TestBed.createComponent(OwnerCalendar).componentInstance;
    http = TestBed.inject(HttpTestingController);
    http
      .expectOne(api)
      .flush({ slug: 'bungalow', type: 'bungalow', resort: 'euronat', district: null });
    http.expectOne(`${api}/calendar`).flush(calendar());
  });

  afterEach(() => http.verify());

  it('selects one day on the first click, a whole range on the second', () => {
    page.pick(day('2027-07-10'));
    expect(page.selection()).toEqual({ start: '2027-07-10', end: '2027-07-11' });

    page.pick(day('2027-07-06'));
    expect(page.selection()).toEqual({ start: '2027-07-06', end: '2027-07-11' });
    expect(page.anchor()).toBeNull();
  });

  it('sends the selection and shows the calendar the API sends back', () => {
    page.pick(day('2027-07-10'));
    page.pickWeek();
    page.note.set('  famille ');
    page.block();

    const request = http.expectOne(`${api}/calendar/blocks`);
    expect(request.request.body).toEqual({
      start: '2027-07-10',
      end: '2027-07-17',
      note: 'famille',
    });

    const updated = calendar([
      {
        id: 'b1',
        start: '2027-07-10',
        end: '2027-07-17',
        source: 'block',
        note: 'famille',
        removable: true,
      },
    ]);
    request.flush({ ...updated, upToDate: true }, { status: 201, statusText: 'Created' });

    expect(page.selection()).toBeNull();
    expect(page.calendar()?.upToDate).toBe(true);
    expect(page.upcoming().length).toBe(1);
  });

  it('shows the refusal the API explains', () => {
    page.pick(day('2027-07-10'));
    page.block();

    http
      .expectOne(`${api}/calendar/blocks`)
      .flush(
        { violations: [{ title: 'Ces dates chevauchent une période déjà indisponible.' }] },
        { status: 409, statusText: 'Conflict' },
      );

    expect(page.error()).toBe('Ces dates chevauchent une période déjà indisponible.');
  });
});
