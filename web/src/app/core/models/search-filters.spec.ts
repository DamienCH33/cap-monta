import { convertToParamMap } from '@angular/router';

import {
  amenityLabel,
  filterChips,
  filtersFromQuery,
  filtersToQuery,
  NO_FILTERS,
  SearchFilters,
  toggle,
} from './search-filters';

describe('search filters', () => {
  it("lit l'URL et ignore les valeurs inconnues", () => {
    const filters = filtersFromQuery(
      convertToParamMap({
        type: ['caravane', 'yacht'],
        quartier: 'Europa',
        chambres: '9',
        equipements: ['wifi', 'piscine'],
        tri: 'prix-decroissant',
      }),
    );

    expect(filters).toEqual({
      types: ['caravan'],
      districts: ['Europa'],
      bedrooms: 3,
      amenities: ['wifi'],
      order: 'price_desc',
    });
  });

  it("écrit une URL qui se relit à l'identique", () => {
    const filters: SearchFilters = {
      types: ['mobile_home', 'bungalow'],
      districts: ['Europa', 'Lalande'],
      bedrooms: 2,
      amenities: ['wifi', 'terrasse'],
      order: 'price_asc',
    };

    expect(filtersFromQuery(convertToParamMap(filtersToQuery(filters)))).toEqual(filters);
  });

  it('ajoute puis retire une valeur', () => {
    expect(toggle(['a'], 'b')).toEqual(['a', 'b']);
    expect(toggle(['a', 'b'], 'a')).toEqual(['b']);
  });

  it('prépare une puce par filtre actif, qui sait se retirer', () => {
    const chips = filterChips({ ...NO_FILTERS, districts: ['Europa'], bedrooms: 2 });

    expect(chips.map((chip) => chip.label)).toEqual(['Europa', '2 ch. et +']);
    expect(chips[0].without.districts).toEqual([]);
    expect(chips[1].without.bedrooms).toBe(0);
  });
});

describe('amenityLabel', () => {
  it('shows the label, or the key itself when it is unknown', () => {
    expect(amenityLabel('television')).toBe('Télévision');
    expect(amenityLabel('sauna')).toBe('sauna');
  });
});
