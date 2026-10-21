import { Accommodation } from './accommodation';
import { facebookPost, freeSummerWeeks, nextSummer } from './facebook-post';

const pins = {
  slug: 'mobil-home-pins-6-personnes',
  resort: 'chm',
  type: 'mobile_home',
  district: 'Pins',
  capacity: 6,
  bedrooms: 2,
  surface: 32,
  pricePeriods: [
    {
      startDate: '2026-06-01',
      endDate: '2026-09-01',
      weeklyPrice: 60000,
      nightlyPrice: null,
      minimumNights: 7,
      saturdayArrival: true,
    },
    {
      startDate: '2027-07-03',
      endDate: '2027-08-28',
      weeklyPrice: 65000,
      nightlyPrice: null,
      minimumNights: 7,
      saturdayArrival: true,
    },
    {
      startDate: '2027-09-01',
      endDate: '2027-10-01',
      weeklyPrice: null,
      nightlyPrice: 7000,
      minimumNights: 3,
      saturdayArrival: false,
    },
  ],
} as unknown as Accommodation;

describe('facebookPost', () => {
  it('counts the summer to come: this one until August, the next one from September', () => {
    expect(nextSummer('2027-05-10')).toBe(2027);
    expect(nextSummer('2026-10-21')).toBe(2027);
  });

  it('lists the Saturday-to-Saturday weeks left free, the departure Saturday counting as free', () => {
    const weeks = freeSummerWeeks(
      [
        { start: '2027-06-05', end: '2027-07-03' },
        { start: '2027-07-10', end: '2027-09-25' },
      ],
      '2026-10-21',
    );

    // 3 juillet libre (le séjour précédent part ce jour-là), puis rien jusqu'au 25 septembre.
    expect(weeks).toEqual(['2027-07-03', '2027-09-25']);
  });

  it('writes the home, the coming rates, the free weeks and the tracked link', () => {
    const text = facebookPost(
      pins,
      [],
      'https://cap-monta.up.railway.app/logement/pins',
      '2026-10-21',
    );

    expect(text).toContain('Mobil-home à louer, Pins, CHM Montalivet');
    expect(text).toContain('6 personnes · 2 chambres · 32 m²');
    // Un tarif passé n'apparaît pas.
    expect(text).not.toContain('600 €');
    expect(text).toContain('650 € la semaine');
    expect(text).toContain('70 € la nuit');
    expect(text).toContain("Semaines encore libres l'été 2027");
    expect(text).toContain('https://cap-monta.up.railway.app/logement/pins?utm_source=facebook');
  });

  it('says so when the summer is full, instead of an empty list', () => {
    const text = facebookPost(
      pins,
      [{ start: '2027-01-01', end: '2027-12-31' }],
      'https://x/logement/pins',
      '2026-10-21',
    );

    expect(text).toContain("L'été 2027 est complet");
  });
});
