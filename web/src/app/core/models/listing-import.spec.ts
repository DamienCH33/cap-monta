import {
  otherErrors,
  ProposalPeriod,
  rangesPayload,
  rateHints,
  rateRows,
  ratesPayload,
  rowErrors,
} from './listing-import';

describe('listing import helpers', () => {
  const period = (changes: Partial<ProposalPeriod> = {}): ProposalPeriod => ({
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
    ...changes,
  });

  it('shows prices in euros and sends them back in cents, unticked rows left out', () => {
    const rows = rateRows([period(), period({ label: 'avril 2024', past: true, selected: false })]);

    expect(rows[0]).toMatchObject({ keep: true, weekly: 650, nightly: null });
    expect(ratesPayload(rows)).toEqual([
      {
        start: '2027-07-01',
        end: '2027-08-01',
        weeklyPrice: 65000,
        nightlyPrice: null,
        minimumNights: 1,
        saturdayArrival: false,
      },
    ]);
    expect(rangesPayload([{ keep: false, start: 'a', end: 'b', hint: null }])).toEqual([]);
  });

  it('tells the owner what to check on a row', () => {
    expect(rateHints(period({ unitToConfirm: true }))[0]).toContain('semaine ou la nuit');
    expect(rateHints(period({ stayPrice: 120000, weeklyPrice: 60000 }))[0]).toContain(
      '1200 € pour le séjour entier',
    );
    expect(rateHints(period({ datesMissing: true, start: null }))[0]).toContain('Dates à préciser');
  });

  it('brings the API refusals back to the rows on screen, skipping the unticked ones', () => {
    const rows = [{ keep: true }, { keep: false }, { keep: true }];
    const violations = [
      { propertyPath: 'periods[1].end', message: 'La fin doit venir après le début.' },
      { propertyPath: 'accommodation.type', message: 'Choisissez le type de logement.' },
    ];

    expect(rowErrors(violations, 'periods', rows).get(2)).toEqual([
      'La fin doit venir après le début.',
    ]);
    expect(otherErrors(violations).map((v) => v.propertyPath)).toEqual(['accommodation.type']);
  });
});
