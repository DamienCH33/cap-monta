import { competing, hoursLeft, inboxTab, OwnerBookingRequest, travellers } from './booking-answer';

describe('booking answer helpers', () => {
  const request = (overrides: Partial<OwnerBookingRequest>): OwnerBookingRequest => ({
    id: 'a',
    status: 'pending',
    accommodation: { slug: 'bungalow', title: 'Bungalow · Europa' },
    start: '2027-07-10',
    end: '2027-07-17',
    nights: 7,
    adults: 2,
    children: 0,
    infants: 0,
    pets: 0,
    estimatedPrice: null,
    agreedPrice: null,
    ownerMessage: null,
    createdAt: '2027-06-01T10:00:00+02:00',
    expiresAt: '2027-06-03T10:00:00+02:00',
    respondedAt: null,
    started: false,
    guestName: 'Jeanne',
    message: null,
    outsideRules: false,
    conflict: false,
    contact: null,
    ...overrides,
  });

  it('files a past booking under history, an upcoming one under accepted', () => {
    expect(inboxTab(request({ status: 'accepted' }), '2027-07-01')).toBe('accepted');
    expect(inboxTab(request({ status: 'accepted' }), '2027-07-17')).toBe('history');
    expect(inboxTab(request({ status: 'expired' }), '2027-07-01')).toBe('history');
    expect(inboxTab(request({}), '2027-07-01')).toBe('pending');
  });

  it('lists the travellers the way people say it', () => {
    expect(travellers(request({ adults: 2, children: 2, infants: 1, pets: 1 }))).toBe(
      '2 adultes, 2 enfants, 1 bébé, 1 animal',
    );
    expect(travellers(request({ adults: 1 }))).toBe('1 adulte');
  });

  it('counts the whole hours left, never below zero', () => {
    const now = new Date('2027-06-02T10:30:00+02:00');

    expect(hoursLeft('2027-06-03T10:00:00+02:00', now)).toBe(23);
    expect(hoursLeft('2027-06-01T10:00:00+02:00', now)).toBe(0);
  });

  it('finds the pending requests that accepting this one would decline', () => {
    const chosen = request({ id: 'chosen' });
    const all = [
      chosen,
      request({ id: 'overlap', start: '2027-07-14', end: '2027-07-21' }),
      request({ id: 'touching', start: '2027-07-17', end: '2027-07-24' }),
      request({ id: 'answered', status: 'declined' }),
      request({ id: 'elsewhere', accommodation: { slug: 'caravane', title: 'Caravane' } }),
    ];

    expect(competing(chosen, all).map((r) => r.id)).toEqual(['overlap']);
  });
});
