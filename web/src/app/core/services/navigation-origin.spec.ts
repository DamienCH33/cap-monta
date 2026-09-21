import { NavigationOrigin } from './navigation-origin';

describe('NavigationOrigin', () => {
  it('remembers the accommodation opened from the owner space', () => {
    const origin = new NavigationOrigin();
    origin.openedFromOwnerSpace('mobil-home-europa');

    expect(origin.isFromOwnerSpace('mobil-home-europa')).toBe(true);
    expect(origin.isFromOwnerSpace('bungalow-soleil')).toBe(false);
  });

  it('knows nothing until a link was clicked', () => {
    expect(new NavigationOrigin().isFromOwnerSpace('mobil-home-europa')).toBe(false);
  });
});
