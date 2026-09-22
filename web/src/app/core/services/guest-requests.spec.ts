import { TestBed } from '@angular/core/testing';

import { GuestRequests } from './guest-requests';

describe('GuestRequests', () => {
  const token = (c: string) => c.repeat(48);

  beforeEach(() => localStorage.clear());

  it('remembers a request once, the latest first, across a reload', () => {
    const first = TestBed.inject(GuestRequests);
    first.remember({
      token: token('a'),
      title: 'Bungalow · Hawaï',
      start: '2099-07-03',
      end: '2099-07-10',
    });
    first.remember({
      token: token('b'),
      title: 'Caravane · Europa',
      start: '2099-08-01',
      end: '2099-08-08',
    });
    first.remember({
      token: token('a'),
      title: 'Bungalow · Hawaï',
      start: '2099-07-03',
      end: '2099-07-10',
    });

    TestBed.resetTestingModule();
    const reloaded = TestBed.inject(GuestRequests);

    expect(reloaded.list().map((r) => r.token)).toEqual([token('a'), token('b')]);
  });

  it('forgets finished stays and anything that is not a tracking token', () => {
    localStorage.setItem(
      'cap-monta.demandes',
      JSON.stringify([
        { token: token('a'), title: 'Passé', start: '2020-07-03', end: '2020-07-10' },
        { token: 'pas-un-jeton', title: 'Bricolé', start: '2099-07-03', end: '2099-07-10' },
        { token: token('c'), title: 'À venir', start: '2099-07-03', end: '2099-07-10' },
      ]),
    );

    expect(
      TestBed.inject(GuestRequests)
        .list()
        .map((r) => r.title),
    ).toEqual(['À venir']);
  });

  it('survives a storage that is not JSON', () => {
    localStorage.setItem('cap-monta.demandes', '{oups');

    expect(TestBed.inject(GuestRequests).list()).toEqual([]);
  });
});
