import {
  euros,
  hasTerms,
  NO_TERMS,
  timeLabel,
  TIME_OPTIONS,
  toCents,
  toEuroInput,
} from './stay-terms';

describe('stay terms', () => {
  it('reads euros the French way and back', () => {
    expect(toCents('0,88')).toBe(88);
    expect(toCents(' 300 € ')).toBe(30000);
    expect(toCents('4.5')).toBe(450);
    expect(toCents('')).toBeNull();
    expect(toCents('abc')).toBeNaN();
    expect(toCents('1,234')).toBeNaN();
    expect(toEuroInput(88)).toBe('0,88');
    expect(toEuroInput(30000)).toBe('300');
    expect(euros(88)).toBe('0,88 €');
  });

  it('writes times without the English AM/PM', () => {
    expect(TIME_OPTIONS[0]).toBe('07:00');
    expect(TIME_OPTIONS.at(-1)).toBe('22:00');
    expect(timeLabel('16:00')).toBe('16 h');
    expect(timeLabel('10:30')).toBe('10 h 30');
  });

  it('knows when the owner said nothing', () => {
    expect(hasTerms(NO_TERMS)).toBe(false);
    expect(hasTerms({ ...NO_TERMS, touristTax: 0 })).toBe(true);
  });
});
