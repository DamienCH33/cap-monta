import {
  dayCount,
  lastDay,
  OwnerPeriod,
  overlapsAny,
  rangeBetween,
  saturdayWeek,
} from './owner-calendar';

describe('owner calendar helpers', () => {
  const period = (start: string, end: string): OwnerPeriod => ({
    id: start,
    start,
    end,
    source: 'block',
    note: null,
    removable: true,
  });

  it('turns two clicked days, in any order, into a [) range', () => {
    expect(rangeBetween('2027-07-09', '2027-07-03')).toEqual({
      start: '2027-07-03',
      end: '2027-07-10',
    });
    expect(rangeBetween('2027-07-03', '2027-07-03')).toEqual({
      start: '2027-07-03',
      end: '2027-07-04',
    });
  });

  it('finds the Saturday-to-Saturday week around a day', () => {
    // 7 July 2027 is a Wednesday; 3 July a Saturday.
    expect(saturdayWeek('2027-07-07')).toEqual({ start: '2027-07-03', end: '2027-07-10' });
    expect(saturdayWeek('2027-07-03')).toEqual({ start: '2027-07-03', end: '2027-07-10' });
    expect(saturdayWeek('2027-07-09')).toEqual({ start: '2027-07-03', end: '2027-07-10' });
  });

  it('crosses a month and a daylight-saving change without losing a day', () => {
    // Clocks go back on 31 October 2027.
    expect(saturdayWeek('2027-10-31')).toEqual({ start: '2027-10-30', end: '2027-11-06' });
    expect(dayCount({ start: '2027-10-30', end: '2027-11-06' })).toBe(7);
  });

  it('shows the last day the way people say it', () => {
    expect(lastDay({ start: '2027-07-03', end: '2027-07-10' })).toBe('2027-07-09');
  });

  it('lets a range start on the day another one ends', () => {
    const taken = [period('2027-07-03', '2027-07-10')];

    expect(overlapsAny({ start: '2027-07-10', end: '2027-07-12' }, taken)).toBe(false);
    expect(overlapsAny({ start: '2027-07-09', end: '2027-07-12' }, taken)).toBe(true);
  });
});
