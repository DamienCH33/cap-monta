import { Analytics } from './analytics';

describe('Analytics.cleanPath', () => {
  it('never lets a personal tracking link or a query string leave the site', () => {
    expect(Analytics.cleanPath('/demande/3fa9c0de12?x=1')).toBe('/demande/:lien');
    expect(Analytics.cleanPath('/en/demande/3fa9c0de12/annuler#top')).toBe(
      '/en/demande/:lien/annuler',
    );
    expect(Analytics.cleanPath('/nouveau-mot-de-passe?token=secret')).toBe('/nouveau-mot-de-passe');
    expect(Analytics.cleanPath('/recherche?arrivee=2027-07-01&adultes=2')).toBe('/recherche');
    expect(Analytics.cleanPath('/logement/bungalow-europa')).toBe('/logement/bungalow-europa');
    expect(Analytics.cleanPath('?x=1')).toBe('/');
  });
});
