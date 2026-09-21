import { OwnerFlash } from './owner-flash';

describe('OwnerFlash', () => {
  it('shows a message only once', () => {
    const flash = new OwnerFlash();
    flash.set({ slug: 'mobil-home-europa', message: 'Brouillon enregistré.', tone: 'ok' });

    expect(flash.take()?.message).toBe('Brouillon enregistré.');
    expect(flash.take()).toBeNull();
  });
});
