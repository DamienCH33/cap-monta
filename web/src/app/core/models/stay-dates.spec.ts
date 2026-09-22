import { departureAfter, isReversed, plusDays } from './stay-dates';

describe('stay dates', () => {
  it('adds days across a month and a daylight-saving change', () => {
    expect(plusDays('2027-10-28', 7)).toBe('2027-11-04');
    expect(plusDays('2027-03-27', 1)).toBe('2027-03-28');
  });

  it('keeps a departure after the arrival, and fixes one before it', () => {
    expect(departureAfter('2026-09-29', '2026-10-06')).toBe('2026-10-06');
    expect(departureAfter('2026-09-29', '2026-09-27')).toBe('2026-10-06');
    expect(departureAfter('2026-09-29', '2026-09-29')).toBe('2026-10-06');
    expect(departureAfter('2026-09-29', '')).toBe('2026-10-06');
    expect(departureAfter('', '2026-09-27')).toBe('2026-09-27');
  });

  it('spots a stay entered the wrong way round', () => {
    expect(isReversed('2026-09-29', '2026-09-27')).toBe(true);
    expect(isReversed('2026-09-29', '2026-09-29')).toBe(true);
    expect(isReversed('2026-09-29', '2026-10-06')).toBe(false);
    expect(isReversed(undefined, '2026-10-06')).toBe(false);
  });
});
