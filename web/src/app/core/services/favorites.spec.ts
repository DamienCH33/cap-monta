import { TestBed } from '@angular/core/testing';

import { Favorites } from './favorites';

describe('Favorites', () => {
  beforeEach(() => localStorage.clear());

  function create(): Favorites {
    return TestBed.inject(Favorites);
  }

  it('adds, removes and remembers a listing on this device', () => {
    const favorites = create();

    favorites.toggle('bungalow-europa');
    expect(favorites.has('bungalow-europa')).toBe(true);
    expect(JSON.parse(localStorage.getItem('cap-monta.favoris')!)).toEqual(['bungalow-europa']);

    favorites.toggle('bungalow-europa');
    expect(favorites.count()).toBe(0);
  });

  it('keeps a shared selection first, without duplicates or forged values', () => {
    const favorites = create();
    favorites.toggle('mine');

    favorites.addAll(['shared-one', 'mine', '<script>', 'shared-two']);

    expect(favorites.list()).toEqual(['shared-one', 'mine', 'shared-two']);
  });

  it('ignores a corrupted storage', () => {
    localStorage.setItem('cap-monta.favoris', '{not json');

    expect(create().list()).toEqual([]);
  });
});
