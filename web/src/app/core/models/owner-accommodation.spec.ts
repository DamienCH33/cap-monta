import { byCalendarUrgency, checkedLabel, OwnerAccommodation } from './owner-accommodation';

describe('calendar status of an owner accommodation', () => {
  const now = new Date(2027, 6, 15, 10, 0);

  it('says when the calendar was last checked, in days', () => {
    expect(checkedLabel(null, now)).toBe('jamais vérifié');
    expect(checkedLabel(new Date(2027, 6, 15, 8, 0).toISOString(), now)).toBe(
      "vérifié aujourd'hui",
    );
    expect(checkedLabel(new Date(2027, 6, 14, 23, 0).toISOString(), now)).toBe('vérifié hier');
    expect(checkedLabel(new Date(2027, 5, 11, 12, 0).toISOString(), now)).toBe(
      'vérifié il y a 34 jours',
    );
  });

  it('puts the calendars to check first, the never-checked ones on top', () => {
    const item = (slug: string, checkedAt: string | null, upToDate: boolean) =>
      ({ slug, calendarCheckedAt: checkedAt, calendarUpToDate: upToDate }) as OwnerAccommodation;

    const sorted = [
      item('fresh', '2027-07-10T00:00:00+00:00', true),
      item('stale', '2027-05-01T00:00:00+00:00', false),
      item('never', null, false),
    ].sort(byCalendarUrgency);

    expect(sorted.map((a) => a.slug)).toEqual(['never', 'stale', 'fresh']);
  });
});
