import { TestBed } from '@angular/core/testing';

import { District } from '../../core/models/district';
import { NO_FILTERS, SearchFilters } from '../../core/models/search-filters';
import { FilterSheet } from './filter-sheet';

describe('FilterSheet', () => {
  const district = (
    slug: string,
    name: string,
    resort: District['resort'],
    area: District['area'],
  ): District => ({ slug, name, resort, area, accommodationCount: 0 });

  it('keeps the Euronat sectors apart from the CHM districts that have no area', () => {
    const fixture = TestBed.createComponent(FilterSheet);
    fixture.componentRef.setInput('filters', NO_FILTERS);
    fixture.componentRef.setInput('count', 0);
    fixture.componentRef.setInput('districts', [
      district('sables', 'Sables', 'chm', 'dunes'),
      district('nouveau', 'Nouveau', 'chm', null),
      district('euronat-amerique-du-nord', 'Amérique du Nord', 'euronat', null),
      district('euronat-europe', 'Europe', 'euronat', null),
    ]);

    const zones = fixture.componentInstance
      .zones()
      .map((zone) => [zone.key, zone.label, zone.districts.map((item) => item.slug)]);

    expect(zones).toEqual([
      ['dunes', 'CHM · Près des dunes / Océan', ['sables']],
      ['other', 'CHM · Autres quartiers', ['nouveau']],
      ['euronat', 'Euronat', ['euronat-amerique-du-nord', 'euronat-europe']],
    ]);
  });

  it('choosing a resort keeps only its districts, and a second click clears it', () => {
    const fixture = TestBed.createComponent(FilterSheet);
    const emitted: SearchFilters[] = [];
    fixture.componentRef.setInput('filters', {
      ...NO_FILTERS,
      districts: ['sables', 'euronat-europe'],
    });
    fixture.componentRef.setInput('count', 0);
    fixture.componentRef.setInput('districts', [
      district('sables', 'Sables', 'chm', 'dunes'),
      district('euronat-europe', 'Europe', 'euronat', null),
    ]);
    fixture.componentInstance.filtersChange.subscribe((filters) => emitted.push(filters));

    fixture.componentInstance.toggleResort('euronat');
    expect(emitted[0].resort).toBe('euronat');
    expect(emitted[0].districts).toEqual(['euronat-europe']);

    fixture.componentRef.setInput('filters', emitted[0]);
    expect(fixture.componentInstance.zones().map((zone) => zone.key)).toEqual(['euronat']);

    fixture.componentInstance.toggleResort('euronat');
    expect(emitted[1].resort).toBeNull();
  });
});
