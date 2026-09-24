import { TestBed } from '@angular/core/testing';

import { plusDays, today } from '../../core/models/stay-dates';
import { DateRange, nights, shortDate } from './date-range';

describe('DateRange', () => {
  function create(): DateRange {
    return TestBed.createComponent(DateRange).componentInstance;
  }

  it('writes dates the French way', () => {
    expect(shortDate('2027-07-10')).toBe('sam. 10 juil.');
    expect(nights('2027-07-10', '2027-07-17')).toBe(7);
  });

  it('picks the arrival, then the departure, then starts over', () => {
    const range = create();
    const arrival = plusDays(today(), 10);
    const departure = plusDays(today(), 17);
    const day = (iso: string) => ({ iso, label: 1, name: iso, saturday: false, past: false });

    range.open.set(true);
    range.pick(day(arrival));
    expect([range.arrival(), range.departure()]).toEqual([arrival, '']);

    range.pick(day(departure));
    expect([range.arrival(), range.departure()]).toEqual([arrival, departure]);
    expect(range.open()).toBe(false);
    expect(range.label()).toBe(`${shortDate(arrival)} → ${shortDate(departure)}`);

    range.pick(day(plusDays(today(), 3)));
    expect([range.arrival(), range.departure()]).toEqual([plusDays(today(), 3), '']);
  });

  it('never picks a past day and shows two months, Saturdays marked', () => {
    const range = create();
    range.pick({ iso: '2020-01-04', label: 4, name: '', saturday: true, past: true });

    expect(range.arrival()).toBe('');
    expect(range.months()).toHaveLength(2);
    expect(range.months()[0].days.some((d) => d.saturday && d.name.startsWith('samedi'))).toBe(
      true,
    );
  });
});
