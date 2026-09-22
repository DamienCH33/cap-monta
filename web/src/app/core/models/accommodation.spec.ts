import { nightlyFromWeek } from './accommodation';

describe('nightlyFromWeek', () => {
  it('prend 1/7 de la semaine, arrondi à l’euro, comme l’API', () => {
    expect(nightlyFromWeek(65000)).toBe(9300);
    expect(nightlyFromWeek(40000)).toBe(5700);
  });
});
