import {
  AfterViewInit,
  Component,
  computed,
  ElementRef,
  input,
  output,
  viewChild,
} from '@angular/core';

import { District, DISTRICT_AREAS } from '../../core/models/district';
import {
  ACCOMMODATION_TYPES,
  AccommodationTypeKey,
  AMENITIES,
  MAX_BEDROOMS_FILTER,
  NO_FILTERS,
  SearchFilters,
  toggle,
} from '../../core/models/search-filters';

@Component({
  selector: 'cm-filter-sheet',
  templateUrl: './filter-sheet.html',
  styleUrl: './filter-sheet.scss',
})
export class FilterSheet implements AfterViewInit {
  readonly filters = input.required<SearchFilters>();
  readonly count = input.required<number>();
  readonly districts = input<District[]>([]);

  readonly filtersChange = output<SearchFilters>();
  readonly closed = output<void>();

  private readonly dialog = viewChild.required<ElementRef<HTMLDialogElement>>('dialog');

  readonly types = ACCOMMODATION_TYPES;
  readonly amenities = AMENITIES.filter((amenity) => amenity.filter);
  readonly bedroomOptions = [
    { value: 0, label: 'Toutes' },
    ...Array.from({ length: MAX_BEDROOMS_FILTER }, (_, index) => ({
      value: index + 1,
      label: `${index + 1}+`,
    })),
  ];

  /** Les quartiers groupés de la plage vers l'avenue, puis ceux dont la zone n'est pas établie. */
  readonly zones = computed(() => {
    const districts = this.districts();

    return [
      ...DISTRICT_AREAS.map((area) => ({
        key: area.key as string,
        label: area.label,
        districts: districts.filter((district) => district.area === area.key),
      })),
      {
        key: 'other',
        label: 'Autres quartiers',
        districts: districts.filter((district) => district.area === null),
      },
    ].filter((zone) => zone.districts.length > 0);
  });

  ngAfterViewInit(): void {
    const dialog = this.dialog().nativeElement;

    // Absent de certains environnements de test : le panneau s'affiche alors sans fond modal.
    if (typeof dialog.showModal === 'function') {
      dialog.showModal();
    }
  }

  isDistrictSelected(district: District): boolean {
    const selected = this.filters().districts;

    // Un ancien lien peut porter le nom (« Europa ») au lieu du slug (« europa »).
    return selected.includes(district.slug) || selected.includes(district.name);
  }

  toggleType(key: AccommodationTypeKey): void {
    this.update({ types: toggle(this.filters().types, key) });
  }

  toggleDistrict(district: District): void {
    const others = this.filters().districts.filter(
      (value) => value !== district.slug && value !== district.name,
    );

    this.update({
      districts: this.isDistrictSelected(district) ? others : [...others, district.slug],
    });
  }

  setBedrooms(bedrooms: number): void {
    this.update({ bedrooms });
  }

  toggleAmenity(key: string): void {
    this.update({ amenities: toggle(this.filters().amenities, key) });
  }

  clear(): void {
    this.filtersChange.emit({ ...NO_FILTERS, order: this.filters().order });
  }

  close(): void {
    this.dialog().nativeElement.close();
  }

  onDialogClose(): void {
    this.closed.emit();
  }

  onBackdropClick(event: MouseEvent): void {
    // Le contenu est dans .sheet__inner : un clic dont la cible est le <dialog> lui-même vient du fond.
    if (event.target === this.dialog().nativeElement) {
      this.close();
    }
  }

  private update(patch: Partial<SearchFilters>): void {
    this.filtersChange.emit({ ...this.filters(), ...patch });
  }
}
