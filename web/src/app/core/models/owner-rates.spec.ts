import { byYear, nextPeriodDates, RatePeriod, toCents, toEuros } from './owner-rates';

describe('owner rates helpers', () => {
  const period = (start: string, end: string, past = false): RatePeriod => ({
    id: start,
    start,
    end,
    weeklyPrice: 65000,
    nightlyPrice: null,
    minimumNights: 7,
    saturdayArrival: true,
    past,
  });

  it('converts euros typed in the form to cents, and back', () => {
    expect(toCents(650)).toBe(65000);
    expect(toCents('89.5')).toBe(8950);
    expect(toCents('')).toBeNull();
    expect(toCents(null)).toBeNull();
    expect(toEuros(65000)).toBe(650);
    expect(toEuros(null)).toBeNull();
  });

  it('suggests the next period right where the last one ends', () => {
    const periods = [period('2027-07-03', '2027-07-10'), period('2026-07-04', '2026-07-11', true)];

    expect(nextPeriodDates(periods, '2026-09-22')).toEqual({
      start: '2027-07-10',
      end: '2027-07-17',
    });
    expect(nextPeriodDates([], '2026-09-22')).toEqual({ start: '2026-09-22', end: '2026-09-29' });
  });

  it('groups the periods season by season', () => {
    const groups = byYear([period('2028-07-01', '2028-07-08'), period('2027-07-03', '2027-07-10')]);

    expect(groups.map((g) => g.year)).toEqual([2027, 2028]);
  });
});
